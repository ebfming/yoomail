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
 * @psalm-api
 */
class Version1096Date20210407150016 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('yoomail_accounts')) {
			$table = $schema->getTable('yoomail_accounts');
			$table->modifyColumn('provisioned', [
				'notnull' => false,
				'default' => false
			]);
			$table->modifyColumn('show_subscribed_only', [
				'notnull' => false,
				'default' => false
			]);
			$table = $schema->getTable('yoomail_accounts');
			$table->modifyColumn('sieve_enabled', [
				'notnull' => false,
				'default' => false
			]);
		}

		if ($schema->hasTable('yoomail_classifiers')) {
			$table = $schema->getTable('yoomail_classifiers');
			$table->modifyColumn('active', [
				'notnull' => false,
				'default' => false
			]);
		}

		if ($schema->hasTable('yoomail_mailboxes')) {
			$table = $schema->getTable('yoomail_mailboxes');
			$table->modifyColumn('selectable', [
				'notnull' => false,
				'default' => false
			]);
			$table->modifyColumn('sync_in_background', [
				'notnull' => false,
				'default' => false
			]);
		}

		if ($schema->hasTable('yoomail_messages')) {
			$table = $schema->getTable('yoomail_messages');
			$table->modifyColumn('flag_answered', [
				'notnull' => false,
				'default' => false
			]);
			$table->modifyColumn('flag_deleted', [
				'notnull' => false,
				'default' => false
			]);
			$table->modifyColumn('flag_draft', [
				'notnull' => false,
				'default' => false
			]);
			$table->modifyColumn('flag_flagged', [
				'notnull' => false,
				'default' => false
			]);
			$table->modifyColumn('flag_seen', [
				'notnull' => false,
				'default' => false
			]);
			$table->modifyColumn('flag_forwarded', [
				'notnull' => false,
				'default' => false
			]);
			$table->modifyColumn('flag_junk', [
				'notnull' => false,
				'default' => false
			]);
			$table->modifyColumn('flag_notjunk', [
				'notnull' => false,
				'default' => false
			]);
			$table->modifyColumn('flag_important', [
				'notnull' => false,
				'default' => false
			]);
			$table->modifyColumn('flag_mdnsent', [
				'notnull' => false,
				'default' => false
			]);
			$table->modifyColumn('structure_analyzed', [
				'notnull' => false,
				'default' => false
			]);
		}

		return $schema;
	}
}
