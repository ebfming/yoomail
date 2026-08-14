<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Service;

use OCA\YooMail\Db\AliasMapper;
use OCA\YooMail\Db\CollectedAddressMapper;
use OCA\YooMail\Db\MailAccountMapper;
use OCA\YooMail\Db\MailboxMapper;
use OCA\YooMail\Db\MessageMapper;
use OCA\YooMail\Db\MessageRetentionMapper;
use OCA\YooMail\Db\MessageSnoozeMapper;
use OCA\YooMail\Db\TagMapper;
use OCA\YooMail\Support\PerformanceLogger;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

class CleanupService {
	private MailAccountMapper $mailAccountMapper;

	/** @var AliasMapper */
	private $aliasMapper;

	/** @var MailboxMapper */
	private $mailboxMapper;

	/** @var MessageMapper */
	private $messageMapper;

	/** @var CollectedAddressMapper */
	private $collectedAddressMapper;

	/** @var TagMapper */
	private $tagMapper;

	private MessageRetentionMapper $messageRetentionMapper;

	private MessageSnoozeMapper $messageSnoozeMapper;

	private ITimeFactory $timeFactory;

	public function __construct(MailAccountMapper $mailAccountMapper,
		AliasMapper $aliasMapper,
		MailboxMapper $mailboxMapper,
		MessageMapper $messageMapper,
		CollectedAddressMapper $collectedAddressMapper,
		TagMapper $tagMapper,
		MessageRetentionMapper $messageRetentionMapper,
		MessageSnoozeMapper $messageSnoozeMapper,
		ITimeFactory $timeFactory) {
		$this->aliasMapper = $aliasMapper;
		$this->mailboxMapper = $mailboxMapper;
		$this->messageMapper = $messageMapper;
		$this->collectedAddressMapper = $collectedAddressMapper;
		$this->tagMapper = $tagMapper;
		$this->messageRetentionMapper = $messageRetentionMapper;
		$this->messageSnoozeMapper = $messageSnoozeMapper;
		$this->mailAccountMapper = $mailAccountMapper;
		$this->timeFactory = $timeFactory;
	}

	public function cleanUp(LoggerInterface $logger): void {
		$task = (new PerformanceLogger(
			$this->timeFactory,
			$logger
		))->start('clean up');
		$this->mailAccountMapper->deleteProvisionedOrphanAccounts();
		$task->step('delete orphan provisioned accounts');
		$this->aliasMapper->deleteOrphans();
		$task->step('delete orphan aliases');
		$this->mailboxMapper->deleteOrphans();
		$task->step('delete orphan mailboxes');
		$this->messageMapper->deleteOrphans();
		$task->step('delete orphan messages');
		$this->collectedAddressMapper->deleteOrphans();
		$task->step('delete orphan collected addresses');
		$this->tagMapper->deleteOrphans();
		$task->step('delete orphan tags');
		$this->tagMapper->deleteDuplicates();
		$task->step('delete duplicate tags');
		$this->messageRetentionMapper->deleteOrphans();
		$task->step('delete expired messages');
		$this->messageSnoozeMapper->deleteOrphans();
		$task->step('delete orphan snoozes');
		$task->end();
	}
}
