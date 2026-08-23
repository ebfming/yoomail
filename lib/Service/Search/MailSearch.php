<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Service\Search;

use Horde_Imap_Client;
use OCA\YooMail\Account;
use OCA\YooMail\Contracts\IMailSearch;
use OCA\YooMail\Db\Mailbox;
use OCA\YooMail\Db\Message;
use OCA\YooMail\Db\MessageMapper;
use OCA\YooMail\Exception\ClientException;
use OCA\YooMail\Exception\MailboxLockedException;
use OCA\YooMail\Exception\MailboxNotCachedException;
use OCA\YooMail\Exception\ServiceException;
use OCA\YooMail\IMAP\PreviewEnhancer;
use OCA\YooMail\IMAP\Search\Provider as ImapSearchProvider;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;

class MailSearch implements IMailSearch {
	/** @var FilterStringParser */
	private $filterStringParser;

	/** @var ImapSearchProvider */
	private $imapSearchProvider;

	/** @var MessageMapper */
	private $messageMapper;

	/** @var PreviewEnhancer */
	private $previewEnhancer;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var LoggerInterface */
	private LoggerInterface $logger;

	public function __construct(FilterStringParser $filterStringParser,
		ImapSearchProvider $imapSearchProvider,
		MessageMapper $messageMapper,
		PreviewEnhancer $previewEnhancer,
		ITimeFactory $timeFactory,
		LoggerInterface $logger) {
		$this->filterStringParser = $filterStringParser;
		$this->imapSearchProvider = $imapSearchProvider;
		$this->messageMapper = $messageMapper;
		$this->previewEnhancer = $previewEnhancer;
		$this->timeFactory = $timeFactory;
		$this->logger = $logger;
	}

	#[\Override]
	public function findMessage(Account $account,
		Mailbox $mailbox,
		Message $message): Message {
		$processed = $this->previewEnhancer->process(
			$account,
			$mailbox,
			[$message],
			false,
			null,
			true,
			true
		);
		if ($processed === []) {
			throw new DoesNotExistException('Message does not exist');
		}
		return $processed[0];
	}

	/**
	 * @param Account $account
	 * @param Mailbox $mailbox
	 * @param string $sortOrder
	 * @param string|null $filter
	 * @param int|null $cursor
	 * @param int|null $limit
	 * @param string|null $view
	 *
	 * @return Message[]
	 *
	 * @throws ClientException
	 * @throws ServiceException
	 */
	#[\Override]
	public function findMessages(Account $account,
		Mailbox $mailbox,
		string $sortOrder,
		?string $filter,
		?int $cursor,
		?int $limit,
		?string $userId,
		?string $view): array {
		if ($mailbox->hasLocks($this->timeFactory->getTime())) {
			// Another process is currently syncing this mailbox (e.g. the
			// realtime background sync). Serving the local snapshot instead of
			// failing keeps the list instantly readable — the data is only a
			// few seconds stale and will be refreshed by the next sync.
			$this->logger->debug("Mailbox {$mailbox->getId()} is locked, serving local snapshot");
		}
		// Container mailboxes (selectable=false, e.g. the "Other folders" root
		// on some providers) never hold messages and never get sync tokens —
		// serve an empty list instead of failing with 400 so the UI does not
		// try to initialize a sync for them.
		if (!$mailbox->getSelectable()) {
			return [];
		}
		if (!$mailbox->isCached()) {
			if ($mailbox->getMessages() === 0) {
				return [];
			}
			throw MailboxNotCachedException::from($mailbox);
		}

		$query = $this->filterStringParser->parse($filter);
		if ($cursor !== null) {
			$query->setCursor($cursor);
		}
		if ($view !== null) {
			$query->setThreaded($view === self::VIEW_THREADED);
		}
		// In flagged we don't want anything but flagged messages
		if ($mailbox->isSpecialUse(Horde_Imap_Client::SPECIALUSE_FLAGGED)) {
			$query->addFlag(Flag::is(Flag::FLAGGED));
		}
		// Don't show deleted messages except for trash folders
		if (!$mailbox->isSpecialUse(Horde_Imap_Client::SPECIALUSE_TRASH)) {
			$query->addFlag(Flag::not(Flag::DELETED));
		}

		return $this->previewEnhancer->process(
			$account,
			$mailbox,
			$this->messageMapper->findByIds($account->getUserId(),
				$this->getIdsLocally($account, $mailbox, $query, $sortOrder, $limit),
				$sortOrder,
			),
			true,
			$userId,
			false,
			false
		);
	}

	/**
	 * Find messages across all mailboxes for a user
	 *
	 * @return Message[]
	 *
	 * @throws ServiceException
	 */
	#[\Override]
	public function findMessagesGlobally(
		IUser $user,
		SearchQuery $query,
		?int $limit): array {
		return $this->messageMapper->findByIds($user->getUID(),
			$this->getIdsGlobally($user, $query, $limit),
			'DESC'
		);
	}

	/**
	 * We combine local flag and headers merge with UIDs that match the body search if necessary
	 *
	 * @throws ServiceException
	 */
	private function getIdsLocally(Account $account, Mailbox $mailbox, SearchQuery $query, string $sortOrder, ?int $limit): array {
		if (empty($query->getBodies())) {
			return $this->messageMapper->findIdsByQuery($mailbox, $query, $sortOrder, $limit);
		}

		$fromImap = $this->imapSearchProvider->findMatches(
			$account,
			$mailbox,
			$query
		);
		return $this->messageMapper->findIdsByQuery($mailbox, $query, $sortOrder, $limit, $fromImap);
	}

	/**
	 * We combine local flag and headers merge with UIDs that match the body search if necessary
	 *
	 * @todo find a way to search across all mailboxes efficiently without iterating over each of them and include IMAP results
	 *
	 * @throws ServiceException
	 */
	private function getIdsGlobally(IUser $user, SearchQuery $query, ?int $limit): array {
		return $this->messageMapper->findIdsGloballyByQuery($user, $query, $limit);
	}
}
