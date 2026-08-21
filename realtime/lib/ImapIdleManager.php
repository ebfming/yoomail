<?php

declare(strict_types=1);

namespace OCA\YooMailRealtime;

use OCA\YooMail\Account;
use OCA\YooMail\Db\MailAccount;
use OCA\YooMail\Service\AccountService;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * Loads all usable Mail accounts and starts an IMAP IDLE listener per
 * account's INBOX. Only password-authenticated accounts are supported in
 * the first version.
 *
 * Design note: each account runs its own blocking IDLE loop. In production
 * this is hosted by one Workerman worker process per account (worker count
 * == number of accounts), so a blocking IMAP socket never blocks the
 * WebSocket event loop.
 * @version b-2026.08.21
 */
class ImapIdleManager
{
    /** @var array<int, ImapIdleConnection> */
    private array $connections = [];

    private bool $running = false;

    public function __construct(
        private AccountService $accountService,
        private ICrypto $crypto,
        private RealtimeSyncService $syncService,
        private UserConnectionRegistry $registry,
        private LoggerInterface $logger,
        private int $maxAccounts = 200,
        private int $idleRefreshSeconds = 1500,
    ) {
    }

    public function getSyncService(): RealtimeSyncService
    {
        return $this->syncService;
    }

    /**
     * @return Account[]
     */
    public function collectAccounts(): array
    {
        $accounts = [];
        foreach ($this->accountService->getAllAcounts() as $mailAccount) {
            if (!$mailAccount instanceof MailAccount) {
                continue;
            }
            if (!$mailAccount->canAuthenticateImap()) {
                continue;
            }
            // First version: password auth only
            if ($mailAccount->getAuthMethod() !== 'password') {
                continue;
            }
            if ($mailAccount->getInboundPassword() === null) {
                continue;
            }
            $accounts[] = new Account($mailAccount);
            if (count($accounts) >= $this->maxAccounts) {
                break;
            }
        }
        return $accounts;
    }

    /**
     * Start a blocking IDLE listener for the account at the given index.
     * This is meant to be called from a dedicated Workerman worker process.
     */
    public function listenForAccount(int $index): void
    {
        throw new \LogicException(
            'Legacy ImapIdleManager::listenForAccount() is disabled. Use RealtimeServer + ImapIdleChild instead.'
        );
    }

    /**
     * Start blocking IDLE listeners for all accounts (single process,
     * one after another - only useful for a handful of accounts or testing).
     */
    public function listenAll(): void
    {
        throw new \LogicException(
            'Legacy ImapIdleManager::listenAll() is disabled. Use RealtimeServer + ImapIdleChild instead.'
        );
    }

    public function stop(): void
    {
        $this->running = false;
        foreach ($this->connections as $connection) {
            $connection->stop();
        }
        $this->connections = [];
    }

    private function startAccountListener(Account $account): void
    {
        $ma = $account->getMailAccount();
        $password = $this->crypto->decrypt($ma->getInboundPassword());

        $connection = new ImapIdleConnection(
            $account,
            $ma->getInboundHost(),
            (int)$ma->getInboundPort(),
            $ma->getInboundUser(),
            $password,
            $ma->getInboundSslMode(),
            $this->logger,
            $this->onAccountChange(...),
            $this->idleRefreshSeconds,
        );

        $this->connections[$account->getId()] = $connection;
        $this->logger->info("yoomail-realtime: listener started for account {$account->getId()} ({$ma->getInboundUser()})");

        // Blocking; returns only when the listener is stopped/gives up
        $connection->run();
    }

    /**
     * Callback invoked when an IDLE connection signals a change.
     */
    private function onAccountChange(Account $account): bool
    {
        $userId = $account->getUserId();
        $ok = $this->syncService->syncAccount($account, false);

        // Notify online clients of this user
        $this->registry->pushToUser($userId, [
            'type' => 'mailbox-changed',
            'accountId' => $account->getId(),
            'reason' => 'new-message',
            'timestamp' => time(),
        ]);

        $this->logger->info("yoomail-realtime: notified user {$userId} about account {$account->getId()} changes (sync=" . ($ok ? 'ok' : 'fail') . ")");
        return $ok;
    }
}
