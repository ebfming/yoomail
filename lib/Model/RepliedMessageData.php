<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\YooMail\Model;

use OCA\YooMail\Account;
use OCA\YooMail\Db\Message;

/**
 * An immutable DTO that holds information about a message that is replied to
 *
 * @psalm-immutable
 */
final class RepliedMessageData {
	/** @var Account */
	private $account;

	/** @var Message */
	private $message;

	public function __construct(Account $account, Message $message) {
		$this->account = $account;
		$this->message = $message;
	}

	public function getAccount(): Account {
		return $this->account;
	}

	public function getMessage(): Message {
		return $this->message;
	}
}
