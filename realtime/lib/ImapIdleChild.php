<?php

declare(strict_types=1);

namespace OCA\YooMailRealtime;

use OCA\YooMail\Account;
use Psr\Log\LoggerInterface;

/**
 * Runs in a forked child process. It owns one blocking IMAP IDLE connection
 * for one account's INBOX. When a change is detected it:
 *   1. immediately notifies the browser (signal A: mailbox-changed),
 *   2. kicks off the actual mailbox sync in the background — a separate
 *      `occ yoomail:account:sync --mailbox=<id> --notify-ipc=<ipc>` process
 *      that writes the new messages into the local DB and then reports
 *      "sync-done" (signal B) itself, so the IDLE loop is never blocked.
 */
class ImapIdleChild
{
    private bool $stopped = false;

    /** @var array<int, resource> */
    private array $procs = [];

    public function __construct(
        private Account $account,
        private int $mailboxId,
        private string $ipcHost,
        private int $ipcPort,
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
            $this->reapChildren();
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

    /**
     * Signal A + background sync. Never blocks the IDLE loop.
     */
    private function handleChange(): void
    {
        $accountId = $this->account->getId();
        $userId = $this->account->getUserId();

        // 1. Immediately tell the browser that the mailbox changed (signal A).
        //    The frontend must NOT start an IMAP sync on this signal — the
        //    data is not in the local DB yet; it waits for signal B instead.
        $this->reportToMaster([
            'type' => 'mailbox-changed',
            'userId' => $userId,
            'accountId' => $accountId,
            'mailboxId' => $this->mailboxId,
            'sync' => 'started',
            'timestamp' => time(),
        ]);

        // 2. Run the sync in a detached background process. The occ command
        //    sends the "sync-done" signal (B) to the IPC worker itself once
        //    it finished, so we don't have to wait for it here.
        $this->forkBackgroundSync();
    }

    /**
     * Start `occ yoomail:account:sync --mailbox=... --notify-ipc=...` without
     * blocking. stdout/stderr go to /dev/null so the pipes can never fill up.
     */
    private function forkBackgroundSync(): void
    {
        $occPath = \OC::$SERVERROOT . '/occ';
        $cmd = [
            PHP_BINARY,
            $occPath,
            'yoomail:account:sync',
            (string)$this->account->getId(),
            '--mailbox=' . $this->mailboxId,
            '--notify-ipc=' . $this->ipcHost . ':' . $this->ipcPort,
        ];
        $cmdStr = implode(' ', array_map('escapeshellarg', $cmd));

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        $proc = @proc_open($cmdStr, $descriptorSpec, $pipes);
        if (!is_resource($proc)) {
            $this->logger->warning("yoomail-realtime: [child] failed to fork background sync for account {$this->account->getId()}");
            return;
        }
        fclose($pipes[0]);
        $this->procs[] = $proc;
        $this->logger->info("yoomail-realtime: [child] forked background sync for account {$this->account->getId()} mailbox {$this->mailboxId}");
    }

    /**
     * Reap finished occ sub-processes so no zombie processes / leaked
     * proc handles accumulate in this long-running worker.
     */
    private function reapChildren(): void
    {
        foreach ($this->procs as $i => $proc) {
            $status = proc_get_status($proc);
            if ($status === false || !$status['running']) {
                proc_close($proc);
                unset($this->procs[$i]);
            }
        }
    }

    private function reportToMaster(array $payload): void
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
        fwrite($fp, json_encode($payload) . "\n");
        fclose($fp);
    }

    private function sleep(int $seconds): void
    {
        \sleep($seconds);
    }
}
