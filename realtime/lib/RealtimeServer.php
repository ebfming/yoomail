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
 *   IDLE worker detects change -> syncs -> sends signal to IPC worker
 *   IPC worker publishes 'mailbox-changed' on the channel
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

        if (!class_exists(Worker::class)) {
            $this->logger->error('yooyoomail-realtime: Workerman is not installed');
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
            ChannelClient::on('mailbox-changed', function ($eventData): void {
                $this->handleChannelEvent($eventData);
            });
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
        $accountCount = count($accounts);
        $this->logger->info("yooyoomail-realtime: starting {$accountCount} IDLE worker(s)");

        if ($accountCount > 0) {
            $idleWorker = new Worker();
            $idleWorker->count = $accountCount;
            $idleWorker->name = 'mail-idle';
            $idleWorker->onWorkerStart = function (Worker $worker) use ($accounts, $wsHost, $ipcPort): void {
                $index = $worker->id;
                if (!isset($accounts[$index])) {
                    while (true) {
                        \sleep(3600);
                    }
                }
                echo "[yoomail-realtime] IDLE worker id={$index} for account {$accounts[$index]->getId()}\n";
                $this->startIdleWorker($accounts[$index], $wsHost, $ipcPort);
            };
        }

        Worker::runAll();
    }

    /**
     * Runs inside an IDLE worker process. Blocks forever.
     */
    private function startIdleWorker(\OCA\YooMail\Account $account, string $wsHost, int $ipcPort): void
    {
        $child = new ImapIdleChild(
            $account,
            $wsHost,
            $ipcPort,
            $this->syncService,
            $this->logger,
            (int)$this->config->getAppValue('yoomail', 'realtime_idle_refresh_seconds', '1500'),
        );
        $child->run();
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
        if ($type === 'mailbox-changed') {
            ChannelClient::publish('mailbox-changed', $payload);
            echo "[yoomail-realtime] IPC forwarded mailbox-changed to channel\n";
        }
    }

    /**
     * Called in the WS worker: push to online clients.
     */
    private function handleChannelEvent(array $payload): void
    {
        $userId = (string)($payload['userId'] ?? '');
        $accountId = (int)($payload['accountId'] ?? 0);
        $this->logger->info("yooyoomail-realtime: channel mailbox-changed user=$userId account=$accountId");
        $this->registry->pushToUser($userId, [
            'type' => 'mailbox-changed',
            'accountId' => $accountId,
            'reason' => 'new-message',
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
