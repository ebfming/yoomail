<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Command;

use OCA\YooMail\Account;
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

	private AccountService $accountService;
	private MailboxSync $mailboxSync;
	private ImapToDbSynchronizer $syncService;
	private LoggerInterface $logger;
	private IMAPClientFactory $clientFactory;

	public function __construct(AccountService $service,
		MailboxSync $mailboxSync,
		ImapToDbSynchronizer $messageSync,
		LoggerInterface $logger,
		IMAPClientFactory $clientFactory) {
		parent::__construct();

		$this->accountService = $service;
		$this->mailboxSync = $mailboxSync;
		$this->syncService = $messageSync;
		$this->logger = $logger;
		$this->clientFactory = $clientFactory;
	}

	/**
	 * @return void
	 */
	protected function configure() {
		$this->setName('yoomail:account:sync');
		$this->setDescription('Synchronize an IMAP account');
		$this->addArgument(self::ARGUMENT_ACCOUNT_ID, InputArgument::REQUIRED);
		$this->addOption(self::OPTION_FORCE, 'f', InputOption::VALUE_NONE);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$accountId = (int)$input->getArgument(self::ARGUMENT_ACCOUNT_ID);
		$force = $input->getOption(self::OPTION_FORCE);

		try {
			$account = $this->accountService->findById($accountId);
		} catch (DoesNotExistException $e) {
			$output->writeln("<error>Account $accountId does not exist</error>");

			return 1;
		}

		$this->sync($account, $force, $output);

		$mbs = (int)(memory_get_peak_usage() / 1024 / 1024);
		$output->writeln('<info>' . $mbs . 'MB of memory used</info>');

		return 0;
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
}
