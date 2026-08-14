<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Dashboard;

use OCA\YooMail\Service\Search\Flag;
use OCA\YooMail\Service\Search\GlobalSearchQuery;
use OCA\YooMail\Service\Search\SearchQuery;

class ImportantMailWidget extends MailWidget {
	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getId(): string {
		return 'yoomail';
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getTitle(): string {
		return $this->l10n->t('Important mail');
	}

	#[\Override]
	public function getSearchQuery(string $userId): SearchQuery {
		$query = new GlobalSearchQuery();
		$query->addFlag(Flag::is(Flag::IMPORTANT));
		$query->setExcludeMailboxIds($this->getMailboxIdsToExclude($userId));
		return $query;
	}
}
