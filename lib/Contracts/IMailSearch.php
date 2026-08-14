<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Contracts;

use OCA\YooMail\Account;
use OCA\YooMail\Db\Mailbox;
use OCA\YooMail\Db\Message;
use OCA\YooMail\Exception\ClientException;
use OCA\YooMail\Exception\ServiceException;
use OCA\YooMail\Service\Search\SearchQuery;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUser;

interface IMailSearch {
	public const ORDER_NEWEST_FIRST = 'DESC';
	public const ORDER_OLDEST_FIRST = 'ASC';
	public const VIEW_SINGLETON = 'singleton';
	public const VIEW_THREADED = 'threaded';
	/**
	 * @throws DoesNotExistException
	 * @throws ClientException
	 * @throws ServiceException
	 */
	public function findMessage(Account $account,
		Mailbox $mailbox,
		Message $message): Message;

	/**
	 * @param Account $account
	 * @param Mailbox $mailbox
	 * @param string $sortOrder
	 * @param string|null $filter
	 * @param int|null $cursor
	 * @param int|null $limit
	 * @param string|null $userId
	 * @param string|null $view
	 *
	 * @return Message[]
	 *
	 * @throws ClientException
	 * @throws ServiceException
	 */
	public function findMessages(Account $account,
		Mailbox $mailbox,
		string $sortOrder,
		?string $filter,
		?int $cursor,
		?int $limit,
		?string $userId,
		?string $view): array;

	/**
	 * Run a search through all mailboxes of a user.
	 *
	 * @return Message[]
	 *
	 * @throws ClientException
	 * @throws ServiceException
	 */
	public function findMessagesGlobally(IUser $user, SearchQuery $query, ?int $limit): array;
}
