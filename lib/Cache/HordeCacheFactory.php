<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Cache;

use OCA\YooMail\Account;
use OCA\YooMail\Db\MailboxMapper;
use OCA\YooMail\Db\MessageMapper;
use OCP\IConfig;

class HordeCacheFactory {
	public function __construct(
		private MailboxMapper $mailboxMapper,
		private MessageMapper $messageMapper,
		private HordeSyncTokenParser $syncTokenParser,
		private IConfig $config,
	) {
	}

	public function newCache(Account $account): Cache {
		return new Cache(
			$this->messageMapper,
			$this->mailboxMapper,
			$this->syncTokenParser,
			$account,
			$this->config,
		);
	}
}
