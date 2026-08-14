<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\YooMail\Send;

use OCA\YooMail\Account;
use OCA\YooMail\Db\LocalMessage;
use OCA\YooMail\Db\LocalMessageMapper;
use OCA\YooMail\Exception\ServiceException;
use OCA\YooMail\IMAP\IMAPClientFactory;
use OCA\YooMail\Service\Attachment\AttachmentService;
use OCP\DB\Exception;

class Chain {
	public function __construct(
		private SentMailboxHandler $sentMailboxHandler,
		private AntiAbuseHandler $antiAbuseHandler,
		private SendHandler $sendHandler,
		private CopySentMessageHandler $copySentMessageHandler,
		private FlagRepliedMessageHandler $flagRepliedMessageHandler,
		private AttachmentService $attachmentService,
		private LocalMessageMapper $localMessageMapper,
		private IMAPClientFactory $clientFactory,
	) {
	}

	/**
	 * @throws \Throwable
	 * @throws Exception
	 * @throws ServiceException
	 */
	public function process(Account $account, LocalMessage $localMessage): LocalMessage {
		$handlers = $this->sentMailboxHandler;
		$handlers->setNext($this->antiAbuseHandler)
			->setNext($this->sendHandler)
			->setNext($this->copySentMessageHandler)
			->setNext($this->flagRepliedMessageHandler);

		/**
		 * Skip all messages that errored out indeterminedly in the SMTP send.
		 * @see \Horde_Smtp_Exception  for the error codes that are inderminate
		 * They might or might not have been sent already.
		 */
		if ($localMessage->getStatus() === LocalMessage::STATUS_ERROR) {
			throw new ServiceException('Could not send message because a previous send operation produced an unclear sent state.');
		}

		$client = $this->clientFactory->getClient($account);
		try {
			$result = $handlers->process($account, $localMessage, $client);
		} finally {
			$client->logout();
		}

		if ($result->getStatus() === LocalMessage::STATUS_PROCESSED) {
			$this->attachmentService->deleteLocalMessageAttachments($account->getUserId(), $result->getId());
			$this->localMessageMapper->deleteWithRecipients($result);
			return $localMessage;
		}

		return $this->localMessageMapper->update($result);
	}
}
