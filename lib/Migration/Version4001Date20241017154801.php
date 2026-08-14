<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * @codeCoverageIgnore
 */
/**
 * @psalm-api
 */
class Version4001Date20241017154801 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if ($schema->hasTable('yoomail_text_blocks')) {
			return null;
		}
		$table = $schema->createTable('yoomail_text_blocks');

		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('owner', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('title', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('content', Types::TEXT, [
			'notnull' => true,
		]);
		$table->addColumn('preview', Types::TEXT, [
			'notnull' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['owner'], 'yoomail_text_blocks_owner_idx');
		$table->addIndex(['id', 'owner'], 'yoomail_txtblk_id_owner_idx');

		return $schema;
	}
}
