<?php

declare(strict_types=1);

namespace OCA\YooMailRealtime;

use OCA\YooMail\Account;
use OCA\YooMail\IMAP\IMAPClientFactory;
use OCA\YooMail\IMAP\MailboxSync;
use OCA\YooMail\Service\Sync\ImapToDbSynchronizer;
use OCP\EventDispatcher\IEventDispatcher;
use Psr\Log\LoggerInterface;

/**
 * Bridges an IMAP IDLE "doorbell" event into the existing Nextcloud Mail
 * synchronization pipeline, then publishes a mailbox-changed event so that
 * the WebSocket server can notify online clients.
 *
 * IMPORTANT: the actual sync runs in a separate `occ yoomail:account:sync`
 * sub-process. Nextcloud's DB layer is not designed to be reused across
 * many queries inside one long-lived CLI process (unbuffered query /
 * transaction state leaks). Running each sync in its own short-lived
 * process keeps the database connection clean and avoids those errors.
 * @version b-2026.08.13
 */
class RealtimeSyncService
{
    public function __construct(
        private IMAPClientFactory $imapClientFactory,
        private MailboxSync $mailboxSync,
        private ImapToDbSynchronizer $syncService,
        private IEventDispatcher $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Sync a single account after an IDLE wake-up, using a dedicated
     * `occ yoomail:account:sync` sub-process.
     *
     * Returns true on success.
     */
    public function syncAccount(Account $account, bool $syncMailboxes = true): bool
    {
        $accountId = $account->getId();

        // Run occ in a separate process so the DB connection is fresh.
        $occPath = \OC::$SERVERROOT . '/occ';
        $cmd = [PHP_BINARY, $occPath, 'yoomail:account:sync', (string)$accountId];
        $cmdStr = implode(' ', array_map('escapeshellarg', $cmd));

        $this->logger->info("yoomail-realtime: running occ yoomail:account:sync for account {$accountId}");

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmdStr, $descriptorSpec, $pipes);
        if (!is_resource($proc)) {
            $this->logger->error("yoomail-realtime: failed to start occ for account {$accountId}");
            return false;
        }

        // Close stdin, read output
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if ($exitCode !== 0) {
            $this->logger->warning(
                "yoomail-realtime: occ sync for account {$accountId} exited with {$exitCode}: " . trim((string)$stderr)
            );
            return false;
        }

        $this->logger->info("yoomail-realtime: occ sync done for account {$accountId}");
        return true;
    }
}
