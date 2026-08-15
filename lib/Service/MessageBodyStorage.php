<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Service;

use OCA\YooMail\Db\Message;
use Psr\Log\LoggerInterface;

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
 * @version b-2026.08.13
 */
class MessageBodyStorage {
	private const CACHE_VERSION = 2;
	private const MAX_AGE_SECONDS = 2592000;

	private string $baseDir;

	public function __construct(
		private LoggerInterface $logger,
	) {
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
	 * @param Message $message database message entity
	 * @param array $body the getBody() response payload
	 */
	public function save(int $accountId, int $mailboxId, Message $message, array $body): void {
		$dir = $this->mailboxDir($accountId, $mailboxId);
		if (!is_dir($dir) && !@mkdir($dir, 0770, true)) {
			return;
		}
		$messageId = (int)$message->getId();
		$payload = [
			'meta' => [
				'version' => self::CACHE_VERSION,
				'savedAt' => time(),
				'accountId' => $accountId,
				'mailboxId' => $mailboxId,
				'databaseId' => $messageId,
				'uid' => (int)$message->getUid(),
				'updatedAt' => (int)$message->getUpdatedAt(),
				'messageId' => $message->getMessageId(),
			],
			'payload' => $body,
		];
		$content = json_encode(
			$payload,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
		);
		if ($content === false) {
			$this->logger->warning('yoomail: failed to persist message body cache', [
				'accountId' => $accountId,
				'mailboxId' => $mailboxId,
				'messageId' => $messageId,
			]);
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
	public function get(int $accountId, int $mailboxId, Message $message): ?array {
		$messageId = (int)$message->getId();
		$path = $this->mailboxDir($accountId, $mailboxId) . '/' . $messageId . '.json';
		if (!is_file($path)) {
			return null;
		}
		$content = @file_get_contents($path);
		if ($content === false) {
			return null;
		}
		$data = json_decode($content, true);
		if (!is_array($data)) {
			$this->delete($accountId, $mailboxId, $messageId);
			return null;
		}

		if (!isset($data['meta'], $data['payload']) || !is_array($data['meta']) || !is_array($data['payload'])) {
			$this->delete($accountId, $mailboxId, $messageId);
			return null;
		}

		if (!$this->isFresh($data['meta'], $accountId, $mailboxId, $message)) {
			$this->delete($accountId, $mailboxId, $messageId);
			return null;
		}

		return $data['payload'];
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function isFresh(array $meta, int $accountId, int $mailboxId, Message $message): bool {
		$messageId = (int)$message->getId();
		$savedAt = (int)($meta['savedAt'] ?? 0);

		if (($meta['version'] ?? null) !== self::CACHE_VERSION) {
			return false;
		}

		if ($savedAt <= 0 || (time() - $savedAt) > self::MAX_AGE_SECONDS) {
			return false;
		}

		return ($meta['accountId'] ?? null) === $accountId
			&& ($meta['mailboxId'] ?? null) === $mailboxId
			&& ($meta['databaseId'] ?? null) === $messageId
			&& ($meta['uid'] ?? null) === (int)$message->getUid()
			&& ($meta['updatedAt'] ?? null) === (int)$message->getUpdatedAt()
			&& ($meta['messageId'] ?? null) === $message->getMessageId();
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
