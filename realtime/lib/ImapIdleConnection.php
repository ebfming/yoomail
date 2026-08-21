<?php

declare(strict_types=1);

namespace OCA\YooMailRealtime;

use OCA\YooMail\Account;
use Psr\Log\LoggerInterface;

/**
 * Manages a single IMAP IDLE connection for one account's INBOX.
 *
 * It runs inside the Workerman event loop. When the server signals a change
 * (EXISTS/RECENT/EXPUNGE), it calls the provided callback. The callback is
 * expected to perform the actual sync and return true so that the IDLE loop
 * can resume.
 * @version b-2026.08.21
 */
class ImapIdleConnection
{
    private bool $stopped = false;

    public function __construct(
        private Account $account,
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private string $sslMode,
        private LoggerInterface $logger,
        private $onChange,
        private int $idleRefreshSeconds = 1500,
        private int $maxRetries = 10,
    ) {
    }

    /**
     * Start the IDLE loop (blocking, runs in a Workerman coroutine/fiber).
     */
    public function run(): void
    {
        $retry = 0;
        while (!$this->stopped) {
            try {
                $client = new ImapIdleClient(
                    $this->host,
                    $this->port,
                    $this->username,
                    $this->password,
                    $this->sslMode,
                    15,
                );
                $client->connect();
                $client->select('INBOX');
                $this->logger->info("yoomail-realtime: IDLE started for account {$this->account->getId()} INBOX");
                $retry = 0;

                while (!$this->stopped) {
                    $change = $client->waitForChange($this->idleRefreshSeconds);
                    if ($this->stopped) {
                        break;
                    }
                    if ($change !== null) {
                        $this->logger->debug("yoomail-realtime: account {$this->account->getId()} INBOX change: $change");
                        $ok = call_user_func($this->onChange, $this->account);
                        // Re-select after sync is not needed; IDLE resumes automatically.
                        // If the callback reported a failure, reconnect.
                        if ($ok === false) {
                            throw new \RuntimeException('sync callback failed');
                        }
                    }
                    // After timeout (no change), send NOOP and re-enter IDLE to keep alive
                    $client->noop();
                }
            } catch (\Throwable $e) {
                if ($this->stopped) {
                    break;
                }
                $retry++;
                $this->logger->warning(
                    "yoomail-realtime: IDLE connection error for account {$this->account->getId()}: " . $e->getMessage()
                );
                if ($retry > $this->maxRetries) {
                    $this->logger->error("yoomail-realtime: giving up on account {$this->account->getId()} after {$this->maxRetries} retries");
                    break;
                }
                // Backoff: 5s -> 15s -> 60s -> 5min
                $backoff = [5, 15, 60, 300];
                $delay = $backoff[min($retry - 1, count($backoff) - 1)];
                $this->logger->info("yoomail-realtime: reconnecting account {$this->account->getId()} in {$delay}s (retry {$retry})");
                $this->sleep($delay);
            } finally {
                if (isset($client)) {
                    $client->close();
                }
            }
        }
        $this->logger->info("yoomail-realtime: IDLE loop stopped for account {$this->account->getId()}");
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    /**
     * Blocking sleep. In a Workerman event loop use the coroutine-friendly
     * Timer::sleep; fall back to plain sleep() otherwise.
     */
    private function sleep(int $seconds): void
    {
        if (class_exists(\Workerman\Timer::class)) {
            \Workerman\Timer::sleep($seconds);
        } else {
            \sleep($seconds);
        }
    }
}
