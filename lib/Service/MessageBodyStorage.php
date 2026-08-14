<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Service;

use OC\Files\Node\NonExistingFile;
use OCP\Files\IRootFolder;

/**
 * Persists rendered message bodies into the Nextcloud data directory so that
 * opening an email is instant, like a local mail client.
 *
 * Layout (inside the Nextcloud data directory):
 *   <datadirectory>/appdata_<instanceid>/mail/<accountId>_<mailboxId>/<messageId>.json
 *
 * We write to the physical filesystem directly. The IAppData abstraction was
 * found unreliable in this environment (its rootFolder view refused to create
 * files under the appdata mount in CLI/console contexts), so this service
 * resolves the appdata path from the Nextcloud config and manages it directly.
 */
class MessageBodyStorage {
	private string $baseDir;

	public function __construct() {
		// e.g. /web/nextcloud/data/appdata_<instanceid>/mail
		$dataDir = rtrim(\OC::$server->getConfig()->getSystemValueString('datadirectory', '/web/nextcloud/data'), '/');
		$instanceId = \OC::$server->getConfig()->getSystemValueString('instanceid', '');
		$this->baseDir = $dataDir . '/appdata_' . $instanceId . '/mail';
	}

	private function mailboxDir(int $accountId, int $mailboxId): string {
		return $this->baseDir . '/' . $accountId . '_' . $mailboxId;
	}

	/**
	 * Save the rendered body payload for a message.
	 *
	 * @param int $accountId
	 * @param int $mailboxId
	 * @param int $messageId database message id
	 * @param array $body the getBody() response payload
	 */
	public function save(int $accountId, int $mailboxId, int $messageId, array $body): void {
		$dir = $this->mailboxDir($accountId, $mailboxId);
		if (!is_dir($dir) && !@mkdir($dir, 0770, true)) {
			return;
		}
		$content = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($content === false) {
			return;
		}
		$tmp = $dir . '/' . $messageId . '.tmp';
		if (@file_put_contents($tmp, $content) === false) {
			return;
		}
		@rename($tmp, $dir . '/' . $messageId . '.json');
	}

	/**
	 * Load the rendered body payload for a message, or null if not cached.
	 */
	public function get(int $accountId, int $mailboxId, int $messageId): ?array {
		$path = $this->mailboxDir($accountId, $mailboxId) . '/' . $messageId . '.json';
		if (!is_file($path)) {
			return null;
		}
		$content = @file_get_contents($path);
		if ($content === false) {
			return null;
		}
		$data = json_decode($content, true);
		return is_array($data) ? $data : null;
	}

	/**
	 * Delete the cached body for a message.
	 */
	public function delete(int $accountId, int $mailboxId, int $messageId): void {
		$path = $this->mailboxDir($accountId, $mailboxId) . '/' . $messageId . '.json';
		if (is_file($path)) {
			@unlink($path);
		}
	}

	/**
	 * Delete all cached bodies for a mailbox (used on UIDVALIDITY change).
	 */
	public function clearMailbox(int $accountId, int $mailboxId): void {
		$dir = $this->mailboxDir($accountId, $mailboxId);
		if (!is_dir($dir)) {
			return;
		}
		foreach (glob($dir . '/*.json') ?: [] as $file) {
			@unlink($file);
		}
	}

	/**
	 * Delete all cached bodies for an account.
	 */
	public function clearAccount(int $accountId): void {
		if (!is_dir($this->baseDir)) {
			return;
		}
		$prefix = $accountId . '_';
		foreach (glob($this->baseDir . '/' . $prefix . '*', GLOB_ONLYDIR) ?: [] as $dir) {
			foreach (glob($dir . '/*.json') ?: [] as $file) {
				@unlink($file);
			}
			@rmdir($dir);
		}
	}
}
