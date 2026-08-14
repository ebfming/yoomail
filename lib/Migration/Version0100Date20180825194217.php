<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\YooMail\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * @psalm-api
 */
class Version0100Date20180825194217 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		/*
		 * Schema generated from database.xml but required changes for
		 * https://github.com/nextcloud/mail/issues/784 already applied.
		 */

		if (!$schema->hasTable('yoomail_accounts')) {
			$table = $schema->createTable('yoomail_accounts');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('email', Types::STRING, [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('inbound_host', Types::STRING, [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('inbound_port', Types::STRING, [
				'notnull' => true,
				'length' => 6,
				'default' => '',
			]);
			$table->addColumn('inbound_ssl_mode', Types::STRING, [
				'notnull' => true,
				'length' => 10,
				'default' => '',
			]);
			$table->addColumn('inbound_user', Types::STRING, [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('inbound_password', Types::STRING, [
				'notnull' => true,
				'length' => 2048,
				'default' => '',
			]);
			$table->addColumn('outbound_host', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('outbound_port', Types::STRING, [
				'notnull' => false,
				'length' => 6,
			]);
			$table->addColumn('outbound_ssl_mode', Types::STRING, [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('outbound_user', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('outbound_password', Types::STRING, [
				'notnull' => false,
				'length' => 2048,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'yoomail_userid_index');
		}

		if (!$schema->hasTable('yoomail_coll_addresses')) {
			$table = $schema->createTable('yoomail_coll_addresses');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('email', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('display_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'yoomail_coll_addr_userid_index');
			$table->addIndex(['email'], 'yoomail_coll_addr_email_index');
		}

		if (!$schema->hasTable('yoomail_aliases')) {
			$table = $schema->createTable('yoomail_aliases');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('account_id', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('alias', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('yoomail_attachments')) {
			$table = $schema->createTable('yoomail_attachments');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('file_name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('created_at', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'yoomail_attach_userid_index');
		}

		return $schema;
	}
}
