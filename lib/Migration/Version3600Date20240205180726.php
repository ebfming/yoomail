<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * @psalm-api
 */
class Version3600Date20240205180726 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$mailboxesTable = $schema->getTable('yoomail_messages');

		if ($mailboxesTable->hasIndex('yoomail_messages_mailbox_id')) {
			$mailboxesTable->dropIndex('yoomail_messages_mailbox_id');
		}

		$mailboxesTable = $schema->getTable('yoomail_smime_certificates');

		if ($mailboxesTable->hasIndex('yoomail_smime_certs_id_uid_idx')) {
			$mailboxesTable->dropIndex('yoomail_smime_certs_id_uid_idx');
		}

		$mailboxesTable = $schema->getTable('yoomail_tags');

		if ($mailboxesTable->hasIndex('yoomail_msg_tags_usr_id_index')) {
			$mailboxesTable->dropIndex('yoomail_msg_tags_usr_id_index');
		}

		return $schema;
	}
}
