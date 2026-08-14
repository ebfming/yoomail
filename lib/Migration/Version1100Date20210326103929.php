<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * @link https://github.com/nextcloud/mail/issues/4833
 */
/**
 * @psalm-api
 */
class Version1100Date20210326103929 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('yoomail_message_tags')) {
			$table = $schema->getTable('yoomail_message_tags');
			if ($table->hasIndex('yoomail_msg_tag_id_idx')) {
				$table->dropIndex('yoomail_msg_tag_id_idx');
			}
			if (!$table->hasIndex('yoomail_msg_imap_id_idx')) {
				$table->addIndex(['imap_message_id'], 'yoomail_msg_imap_id_idx', [], ['lengths' => [128]]);
			}
		}
		return $schema;
	}
}
