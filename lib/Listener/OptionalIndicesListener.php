<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Listener;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use OCP\DB\Events\AddMissingIndicesEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IDBConnection;

/**
 * @template-implements IEventListener<Event|OptionalIndicesListener>
 */
class OptionalIndicesListener implements IEventListener {

	/** @var IConfig */
	private $config;
	private IDBConnection $connection;

	public function __construct(IConfig $config,
		IDBConnection $connection) {
		$this->config = $config;
		$this->connection = $connection;
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof AddMissingIndicesEvent)) {
			return;
		}

		if (version_compare($this->config->getSystemValue('version', '0.0.0'), '28.0.0', '>=')) {
			$event->addMissingIndex(
				'yoomail_messages',
				'yoomail_messages_msgid_idx',
				['message_id'],
				[
					'lengths' => [128],
				],
			);
		}

		$event->addMissingIndex(
			'yoomail_messages',
			'yoomail_messages_strucanalyz_idx',
			['structure_analyzed']
		);

		$event->addMissingIndex(
			'yoomail_classifiers',
			'yoomail_class_creat_idx',
			['created_at']
		);

		$event->addMissingIndex(
			'yoomail_accounts',
			'yoomail_acc_prov_idx',
			['provisioning_id']
		);

		$event->addMissingIndex(
			'yoomail_aliases',
			'yoomail_alias_accid_idx',
			['account_id']
		);

		$event->replaceIndex(
			'yoomail_messages',
			['yoomail_messages_mb_id_uid'],
			'yoomail_messages_mb_id_uid_uidx',
			['mailbox_id', 'uid'],
			true
		);

		$event->replaceIndex(
			'yoomail_smime_certificates',
			['yoomail_smime_certs_uid_idx'],
			'yoomail_smime_certs_uid_email_idx',
			['user_id', 'email_address'],
			false
		);

		$event->replaceIndex(
			'yoomail_trusted_senders',
			['yoomail_trusted_senders_type'],
			'yoomail_trusted_senders_idx',
			['user_id', 'email', 'type'],
			false
		);

		$event->replaceIndex(
			'yoomail_coll_addresses',
			['yoomail_coll_addr_userid_index', 'yoomail_coll_addr_email_index'],
			'yoomail_coll_idx',
			['user_id', 'email', 'display_name'],
			false
		);

		/**
		 * This index may be missing on Postgres - we add it back on all DBs nevertheless
		 * @see \OCA\YooMail\Migration\Version1130Date20220412111833::changeSchema for the different lengths
		 */
		if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
			$event->addMissingIndex(
				'yoomail_messages',
				'yoomail_msg_thrd_root_snt_idx',
				['mailbox_id', 'thread_root_id', 'sent_at'],
			);
		} else {
			$event->addMissingIndex(
				'yoomail_messages',
				'yoomail_msg_thrd_root_snt_idx',
				['mailbox_id', 'thread_root_id', 'sent_at'],
				['lengths' => [null, 64, null]],
			);
		}

		$event->replaceIndex(
			'yoomail_recipients',
			['yoomail_recipient_email_idx'],
			'yoomail_recip_eml_type_mid_idx',
			['email', 'type', 'message_id'],
			false,
		);
	}

}
