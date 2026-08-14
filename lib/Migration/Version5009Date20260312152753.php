<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version5009Date20260312152753 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('yoomail_delegations')) {
			$table = $schema->createTable('yoomail_delegations');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('account_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['account_id', 'user_id'], 'yoomail_deleg_acc_user_uniq');
			$table->addIndex(['user_id', 'account_id'], 'yoomail_deleg_user_acc_idx');

			if ($schema->hasTable('yoomail_accounts')) {
				$table->addForeignKeyConstraint(
					$schema->getTable('yoomail_accounts'),
					['account_id'],
					['id'],
					[
						'onDelete' => 'CASCADE',
					]
				);
			}
		}

		return $schema;
	}

}
