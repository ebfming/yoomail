<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Command;

use Horde_Imap_Client;
use OCA\YooMail\Account;
use OCA\YooMail\Db\Mailbox;
use OCA\YooMail\Db\MailboxMapper;
use OCA\YooMail\Exception\IncompleteSyncException;
use OCA\YooMail\Exception\ServiceException;
use OCA\YooMail\IMAP\IMAPClientFactory;
use OCA\YooMail\IMAP\MailboxSync;
use OCA\YooMail\Service\AccountService;
use OCA\YooMail\Service\Sync\ImapToDbSynchronizer;
use OCA\YooMail\Support\ConsoleLoggerDecorator;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function memory_get_peak_usage;
use function memory_get_usage;

final class SyncAccount extends Command {
	public const ARGUMENT_ACCOUNT_ID = 'account-id';
	public const OPTION_FORCE = 'force';
	public const OPTION_MAILBOX = 'mailbox';
	public const OPTION_NOTIFY_IPC = 'notify-ipc';

	private AccountService $accountService;
	private MailboxSync $mailboxSync;
	private ImapToDbSynchronizer $syncService;
	private LoggerInterface $logger;
	private IMAPClientFactory $clientFactory;
	private MailboxMapper $mailboxMapper;

	public function __construct(AccountService $service,
		MailboxSync $mailboxSync,
		ImapToDbSynchronizer $messageSync,
		LoggerInterface $logger,
		IMAPClientFactory $clientFactory,
		MailboxMapper $mailboxMapper) {
		parent::__construct();

		$this->accountService = $service;
		$this->mailboxSync = $mailboxSync;
		$this->syncService = $messageSync;
		$this->logger = $logger;
		$this->clientFactory = $clientFactory;
		$this->mailboxMapper = $mailboxMapper;
	}

	/**
	 * @return void
	 */
	protected function configure() {
		$this->setName('yoomail:account:sync');
		$this->setDescription('Synchronize an IMAP account');
		$this->addArgument(self::ARGUMENT_ACCOUNT_ID, InputArgument::REQUIRED);
		$this->addOption(self::OPTION_FORCE, 'f', InputOption::VALUE_NONE);
		$this->addOption(
			self::OPTION_MAILBOX,
			null,
			InputOption::VALUE_REQUIRED,
			'Only synchronize the given mailbox (database id)'
		);
		$this->addOption(
			self::OPTION_NOTIFY_IPC,
			null,
			InputOption::VALUE_REQUIRED,
			'After the sync finished, send a sync-done signal to the given tcp host:port (yoomail realtime service)'
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$accountId = (int)$input->getArgument(self::ARGUMENT_ACCOUNT_ID);
		$force = $input->getOption(self::OPTION_FORCE);
		$mailboxId = $input->getOption(self::OPTION_MAILBOX);
		$notifyIpc = $input->getOption(self::OPTION_NOTIFY_IPC);

		try {
			$account = $this->accountService->findById($accountId);
		} catch (DoesNotExistException $e) {
			$output->writeln("<error>Account $accountId does not exist</error>");

			return 1;
		}

		$ok = true;
		$exitCode = 0;

			if ($mailboxId !== null) {
				// Single-mailbox sync (used by the realtime service after an
				// IMAP IDLE wake-up). Skips the (potentially slow) folder-list
				// sync — only the given mailbox is synchronized.
				try {
					$mailbox = $this->mailboxMapper->findById((int)$mailboxId);
				} catch (DoesNotExistException $e) {
					$output->writeln("<error>Mailbox $mailboxId does not exist</error>");

					return 1;
				}

				if ($mailbox->getAccountId() !== $account->getId()) {
					$ok = false;
					$exitCode = 1;
					$output->writeln(sprintf(
						'<error>Mailbox %d belongs to account %d, not account %d</error>',
						$mailbox->getId(),
						$mailbox->getAccountId(),
						$account->getId()
					));
				} else {
					try {
						$this->syncMailbox($account, $mailbox, $force, $output);
					} catch (\Throwable $e) {
						$ok = false;
						$exitCode = 1;
						$output->writeln('<error>' . $e->getMessage() . '</error>');
					}
				}
			} else {
				try {
					$this->sync($account, $force, $output);
				} catch (\Throwable $e) {
					$ok = false;
					$exitCode = 1;
					$output->writeln('<error>' . $e->getMessage() . '</error>');
				}
			}

		if ($notifyIpc !== null) {
			$this->notifyIpc(
				$notifyIpc,
				$accountId,
				$mailboxId !== null ? (int)$mailboxId : null,
				$ok,
				$account->getUserId()
			);
		}

		$mbs = (int)(memory_get_peak_usage() / 1024 / 1024);
		$output->writeln('<info>' . $mbs . 'MB of memory used</info>');

		return $exitCode;
	}

	/**
	 * Synchronize a single mailbox (realtime fast path).
	 */
	private function syncMailbox(Account $account, Mailbox $mailbox, bool $force, OutputInterface $output): void {
		$consoleLogger = new ConsoleLoggerDecorator($this->logger, $output);
		$client = $this->clientFactory->getClient($account);
		try {
			$this->syncService->sync(
				$account,
				$client,
				$mailbox,
				$consoleLogger,
				Horde_Imap_Client::SYNC_NEWMSGSUIDS
					| Horde_Imap_Client::SYNC_FLAGSUIDS
					| Horde_Imap_Client::SYNC_VANISHEDUIDS,
				null,
				$force,
				true
			);
			// Keep the mailbox unread/total stats fresh even though we skip
			// the folder-list sync (MailboxSync::sync is not run in this path).
			$this->mailboxSync->syncStats($client, $mailbox);
		} finally {
			$client->logout();
		}

		foreach ($this->clientFactory->getLoginStats() as $host => $count) {
			$consoleLogger->debug(sprintf('%d IMAP connection(s) to %s', $count, $host));
		}
	}

	private function sync(Account $account, bool $force, OutputInterface $output): void {
		$consoleLogger = new ConsoleLoggerDecorator(
			$this->logger,
			$output
		);

		try {
			$this->mailboxSync->sync($account, $consoleLogger, $force);
			$this->syncService->syncAccount($account, $consoleLogger, $force);
		} catch (ServiceException $e) {
			if (!($e instanceof IncompleteSyncException)) {
				throw $e;
			}

			$mbs = (int)(memory_get_usage() / 1024 / 1024);
			$output->writeln("<info>Batch of new messages sync'ed. " . $mbs . 'MB of memory in use</info>');
			$this->sync($account, $force, $output);
		}

		foreach ($this->clientFactory->getLoginStats() as $host => $count) {
			$consoleLogger->debug(sprintf('%d IMAP connection(s) to %s', $count, $host));
		}
	}

	/**
	 * Notify the realtime service (IPC worker) that the sync finished, so it
	 * can push a "sync-done" signal to online clients.
	 */
	private function notifyIpc(string $hostPort, int $accountId, ?int $mailboxId, bool $ok, ?string $userId): void {
		$payload = json_encode([
			'type' => 'sync-done',
			'userId' => $userId,
			'accountId' => $accountId,
			'mailboxId' => $mailboxId,
			'sync' => $ok ? 'ok' : 'fail',
			'timestamp' => time(),
		]);

		$fp = @stream_socket_client(
			"tcp://$hostPort",
			$errno,
			$errstr,
			3,
			STREAM_CLIENT_CONNECT
		);
		if ($fp === false) {
			$this->logger->warning("yoomail: could not notify realtime service at $hostPort: $errstr");
			return;
		}
		fwrite($fp, $payload . "\n");
		fclose($fp);
	}
}
