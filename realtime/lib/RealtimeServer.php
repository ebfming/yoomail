<?php

declare(strict_types=1);

namespace OCA\YooMailRealtime;

use Channel\Client as ChannelClient;
use Channel\Server as ChannelServer;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

/**
 * Orchestrates the realtime service using multiple Workerman worker
 * processes connected via a shared Channel server (workerman/channel).
 *
 * Workers:
 *  - mail-channel : the internal pub/sub server on tcp://127.0.0.1:2206
 *  - mail-ws      : WebSocket server for browsers (count=1)
 *  - mail-ipc     : TCP server receiving change signals from IDLE workers
 *  - mail-idle    : one worker per Mail account, running a blocking IMAP IDLE
 *
 * Flow:
 *   IDLE worker detects change -> sends signal A ('mailbox-changed') to the
 *     IPC worker immediately, then forks a background occ sync for that
 *     single INBOX; the occ process reports 'sync-done' (signal B) to the
 *     IPC worker itself once it finished.
 *   IPC worker publishes both events on the channel
 *   WS worker (subscribed to the channel) pushes to online clients
 */
class RealtimeServer
{
    private const CHANNEL_HOST = '127.0.0.1';

    private bool $started = false;

    public function __construct(
        private IConfig $config,
        private UserConnectionRegistry $registry,
        private RealtimeTokenService $tokenService,
        private RealtimeSyncService $syncService,
        private ImapIdleManager $idleManager,
        private LoggerInterface $logger,
    ) {
    }

    public function run(): void
    {
        if ($this->started) {
            return;
        }
        $this->started = true;

        // Mode switch: in 'http' mode the realtime service must not run at
        // all (the frontend polls via classic HTTP sync instead). Defensive
        // guard in case the systemd unit is started anyway.
        $mode = $this->config->getAppValue('yoomail', 'realtime_mode', 'websocket');
        if ($mode !== 'websocket') {
            $this->logger->info('yoomail-realtime: realtime_mode=http, realtime service disabled');
            return;
        }

        if (!class_exists(Worker::class)) {
            $this->logger->error('yoomail-realtime: Workerman is not installed');
            return;
        }
        if (PHP_SAPI !== 'cli') {
            $this->logger->error('yoomail-realtime: this service must be run from the CLI');
            return;
        }

        $wsHost = $this->config->getAppValue('yoomail', 'realtime_ws_host', '127.0.0.1');
        $wsPort = (int)$this->config->getAppValue('yoomail', 'realtime_ws_port', '8789');
        $ipcPort = (int)$this->config->getAppValue('yoomail', 'realtime_ipc_port', '8790');
        $channelPort = (int)$this->config->getAppValue('yoomail', 'realtime_channel_port', '2207');

        // Workerman 默认把 pid 文件写到 start_file 同目录(realtime/),该目录可能
        // 不可被 www-data 写入。改写到 Nextcloud data 目录(www-data 可写),
        // 且使用独立文件名避免与 mail 实时服务的 pid 文件冲突。
        Worker::$pidFile = \OC::$server->get(\OCP\IConfig::class)->getSystemValue('datadirectory', '/web/nextcloud/data')
            . '/yoomail-realtime.pid';

        echo "[yoomail-realtime] ws://{$wsHost}:{$wsPort}  ipc tcp://{$wsHost}:{$ipcPort}  channel tcp://" . self::CHANNEL_HOST . ':' . $channelPort . "\n";

        // --- Channel server (internal pub/sub) ---
        $channelServer = new ChannelServer(self::CHANNEL_HOST, $channelPort);

        $webSocketHandler = new WebSocketServer($this->registry, $this->tokenService);

        // --- WebSocket worker for browsers ---
        $wsWorker = new Worker("websocket://{$wsHost}:{$wsPort}");
        $wsWorker->count = 1;
        $wsWorker->name = 'mail-ws';
        $wsWorker->onWorkerStart = function () use ($channelPort): void {
            ChannelClient::connect(self::CHANNEL_HOST, $channelPort);
            foreach (['mailbox-changed', 'sync-done'] as $event) {
                ChannelClient::on($event, function ($eventData) use ($event): void {
                    $this->handleChannelEvent($eventData);
                });
            }
            echo "[yoomail-realtime] WS worker subscribed to channel\n";
        };
        $wsWorker->onMessage = function ($connection, $data) use ($webSocketHandler): void {
            $webSocketHandler->handleMessage($connection, $data);
        };
        $wsWorker->onClose = function ($connection) use ($webSocketHandler): void {
            $webSocketHandler->handleClose($connection);
        };

        // --- IPC worker: receives change signals from IDLE workers ---
        $ipcWorker = new Worker("tcp://{$wsHost}:{$ipcPort}");
        $ipcWorker->count = 1;
        $ipcWorker->name = 'mail-ipc';
        $ipcWorker->onWorkerStart = function () use ($channelPort): void {
            ChannelClient::connect(self::CHANNEL_HOST, $channelPort);
        };
        $ipcWorker->onMessage = function (TcpConnection $connection, $data): void {
            $this->handleIpcMessage($connection, $data);
        };

        // --- IDLE workers: one per account ---
        $accounts = $this->idleManager->collectAccounts();
        $listeners = $this->buildIdleListeners($accounts);
        $accountCount = count($listeners);
        $this->logger->info("yoomail-realtime: starting {$accountCount} IDLE worker(s)");

        if ($accountCount > 0) {
            $idleWorker = new Worker();
            $idleWorker->count = $accountCount;
            $idleWorker->name = 'mail-idle';
            $idleWorker->onWorkerStart = function (Worker $worker) use ($listeners, $wsHost, $ipcPort): void {
                $index = $worker->id;
                if (!isset($listeners[$index])) {
                    while (true) {
                        \sleep(3600);
                    }
                }
                $listener = $listeners[$index];
                $account = $listener['account'];
                $mailboxId = $listener['mailboxId'];
                echo "[yoomail-realtime] IDLE worker id={$index} for account {$account->getId()} mailbox {$mailboxId}\n";
                $this->startIdleWorker($account, $mailboxId, $ipcPort);
            };
        }

        Worker::runAll();
    }

    /**
     * Runs inside an IDLE worker process. Blocks forever.
     */
    private function startIdleWorker(\OCA\YooMail\Account $account, int $mailboxId, int $ipcPort): void
    {
        $child = new ImapIdleChild(
            $account,
            $mailboxId,
            self::CHANNEL_HOST,
            $ipcPort,
            $this->logger,
            (int)$this->config->getAppValue('yoomail', 'realtime_idle_refresh_seconds', '1500'),
        );
        $child->run();
    }

    /**
     * Resolve account/mailbox pairs before Workerman forks child processes.
     *
     * Nextcloud's DB connection is not fork-safe in long-running Workerman
     * children. Keeping this lookup in the parent avoids stale/crossed DB
     * state such as account/mailbox mismatches after restart.
     *
     * @param \OCA\YooMail\Account[] $accounts
     * @return array<int, array{account: \OCA\YooMail\Account, mailboxId: int}>
     */
    private function buildIdleListeners(array $accounts): array
    {
        $listeners = [];
        foreach ($accounts as $account) {
            $mailboxId = $this->findInboxMailboxId($account);
            if ($mailboxId === null) {
                $this->logger->warning("yoomail-realtime: account {$account->getId()} has no INBOX mailbox, skipping IDLE worker");
                continue;
            }
            $listeners[] = [
                'account' => $account,
                'mailboxId' => $mailboxId,
            ];
            $this->logger->info("yoomail-realtime: configured IDLE account {$account->getId()} mailbox {$mailboxId}");
        }
        return $listeners;
    }

    /**
     * Resolve the database id of the account's INBOX mailbox.
     */
    private function findInboxMailboxId(\OCA\YooMail\Account $account): ?int
    {
        try {
            $mapper = \OC::$server->get(\OCA\YooMail\Db\MailboxMapper::class);
            foreach ($mapper->findAll($account) as $mailbox) {
                if ($mailbox->isInbox()) {
                    if ($mailbox->getAccountId() !== $account->getId()) {
                        $this->logger->warning(sprintf(
                            'yoomail-realtime: ignored mismatched INBOX mailbox %d for account %d, mailbox belongs to account %d',
                            $mailbox->getId(),
                            $account->getId(),
                            $mailbox->getAccountId()
                        ));
                        continue;
                    }
                    return $mailbox->getId();
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning("yoomail-realtime: could not resolve INBOX for account {$account->getId()}: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Called in the IPC worker: forward a change signal to the channel.
     */
    private function handleIpcMessage(TcpConnection $connection, string $data): void
    {
        echo "[yoomail-realtime] IPC onMessage fired: " . trim($data) . "\n";
        $payload = json_decode(trim($data), true);
        if (!is_array($payload)) {
            return;
        }
        $type = $payload['type'] ?? '';
        if ($type === 'mailbox-changed' || $type === 'sync-done') {
            ChannelClient::publish($type, $payload);
            echo "[yoomail-realtime] IPC forwarded $type to channel\n";
        }
    }

    /**
     * Called in the WS worker: push to online clients.
     */
    private function handleChannelEvent(array $payload): void
    {
        $type = (string)($payload['type'] ?? 'mailbox-changed');
        $userId = (string)($payload['userId'] ?? '');
        $accountId = (int)($payload['accountId'] ?? 0);
        $mailboxId = isset($payload['mailboxId']) ? (int)$payload['mailboxId'] : null;
        $this->logger->info("yoomail-realtime: channel $type user=$userId account=$accountId mailbox=" . var_export($mailboxId, true));
        $this->registry->pushToUser($userId, [
            'type' => $type,
            'accountId' => $accountId,
            'mailboxId' => $mailboxId,
            'sync' => $payload['sync'] ?? null,
            'timestamp' => time(),
        ]);
    }

    public function stop(): void
    {
        $this->idleManager->stop();
        if ($this->started) {
            Worker::stopAll();
        }
        $this->started = false;
    }
}
