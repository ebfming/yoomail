<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Events;

use OCA\YooMail\Account;
use OCA\YooMail\Db\Message;
use OCP\EventDispatcher\Event;

/**
 * @psalm-immutable
 */
class OutboxMessageCreatedEvent extends Event {
	/** @var Account */
	private $account;

	/** @var Message */
	private $draft;

	public function __construct(Account $account,
		Message $draft) {
		parent::__construct();
		$this->account = $account;
		$this->draft = $draft;
	}

	public function getAccount(): Account {
		return $this->account;
	}

	public function getDraft(): ?Message {
		return $this->draft;
	}
}
