<?php

declare(strict_types=1);

namespace OCA\YooMailRealtime;

use OCA\YooMail\Account;
use Psr\Log\LoggerInterface;

/**
 * Runs in a forked child process. It owns one blocking IMAP IDLE connection
 * for one account's INBOX. When a change is detected it:
 *   1. performs the actual sync (reusing the standard Mail sync services),
 *   2. reports the change to the master process via the IPC TCP port.
 */
class ImapIdleChild
{
    private bool $stopped = false;

    public function __construct(
        private Account $account,
        private string $ipcHost,
        private int $ipcPort,
        private RealtimeSyncService $syncService,
        private LoggerInterface $logger,
        private int $idleRefreshSeconds = 1500,
        private int $maxRetries = 10,
    ) {
    }

    public function run(): void
    {
        $ma = $this->account->getMailAccount();
        $crypto = \OC::$server->get(\OCP\Security\ICrypto::class);
        $password = $crypto->decrypt($ma->getInboundPassword());

        $retry = 0;
        while (!$this->stopped) {
            $client = null;
            try {
                $client = new ImapIdleClient(
                    $ma->getInboundHost(),
                    (int)$ma->getInboundPort(),
                    $ma->getInboundUser(),
                    $password,
                    $ma->getInboundSslMode(),
                    15,
                );
                $client->connect();
                $client->select('INBOX');
                $this->logger->info("yoomail-realtime: [child] IDLE started for account {$this->account->getId()}");
                $retry = 0;

                while (!$this->stopped) {
                    $change = $client->waitForChange($this->idleRefreshSeconds);
                    if ($this->stopped) {
                        break;
                    }
                    if ($change !== null) {
                        $this->logger->debug("yoomail-realtime: [child] account {$this->account->getId()} change: $change");
                        $this->handleChange();
                    }
                    // Keep alive after idle timeout
                    $client->noop();
                }
            } catch (\Throwable $e) {
                if ($this->stopped) {
                    break;
                }
                $retry++;
                $this->logger->warning(
                    "yoomail-realtime: [child] IDLE error account {$this->account->getId()}: " . $e->getMessage()
                );
                if ($retry > $this->maxRetries) {
                    $this->logger->error("yoomail-realtime: [child] giving up account {$this->account->getId()}");
                    break;
                }
                $backoff = [5, 15, 60, 300];
                $delay = $backoff[min($retry - 1, count($backoff) - 1)];
                $this->sleep($delay);
            } finally {
                $client?->close();
            }
        }
        $this->logger->info("yoomail-realtime: [child] IDLE loop stopped for account {$this->account->getId()}");
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    private function handleChange(): void
    {
        $accountId = $this->account->getId();
        $userId = $this->account->getUserId();

        // 1. Sync using the standard pipeline (inside this child process)
        $ok = $this->syncService->syncAccount($this->account, false);

        // 2. Report to the master process so it can push to WebSocket clients
        $payload = json_encode([
            'type' => 'mailbox-changed',
            'userId' => $userId,
            'accountId' => $accountId,
            'sync' => $ok ? 'ok' : 'fail',
            'timestamp' => time(),
        ]);

        $this->reportToMaster($payload);

        $this->logger->info("yoomail-realtime: [child] reported account $accountId change (sync=" . ($ok ? 'ok' : 'fail') . ")");
    }

    private function reportToMaster(string $payload): void
    {
        $fp = @stream_socket_client(
            "tcp://{$this->ipcHost}:{$this->ipcPort}",
            $errno,
            $errstr,
            3,
            STREAM_CLIENT_CONNECT
        );
        if ($fp === false) {
            $this->logger->warning("yoomail-realtime: [child] IPC connect failed: $errstr");
            return;
        }
        fwrite($fp, $payload . "\n");
        fclose($fp);
    }

    private function sleep(int $seconds): void
    {
        \sleep($seconds);
    }
}
