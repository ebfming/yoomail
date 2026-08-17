<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Service;

use OCA\YooMail\Db\AliasMapper;
use OCA\YooMail\Db\CollectedAddressMapper;
use OCA\YooMail\Db\LocalAttachment;
use OCA\YooMail\Db\LocalAttachmentMapper;
use OCA\YooMail\Db\MailAccountMapper;
use OCA\YooMail\Db\MailboxMapper;
use OCA\YooMail\Db\MessageMapper;
use OCA\YooMail\Db\MessageRetentionMapper;
use OCA\YooMail\Db\MessageSnoozeMapper;
use OCA\YooMail\Db\TagMapper;
use OCA\YooMail\Service\Attachment\AttachmentStorage;
use OCA\YooMail\Support\PerformanceLogger;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class CleanupService {
	private const DEFAULT_BODY_CACHE_RETENTION_DAYS = 30;
	private const DEFAULT_BODY_CACHE_MAX_SIZE_MB = 1024;
	private const DEFAULT_LOCAL_ATTACHMENT_RETENTION_DAYS = 30;
	private const DEFAULT_LOCAL_ATTACHMENT_MAX_SIZE_MB = 512;

	private MailAccountMapper $mailAccountMapper;

	/** @var AliasMapper */
	private $aliasMapper;

	/** @var MailboxMapper */
	private $mailboxMapper;

	/** @var MessageMapper */
	private $messageMapper;

	/** @var CollectedAddressMapper */
	private $collectedAddressMapper;

	/** @var TagMapper */
	private $tagMapper;

	private MessageRetentionMapper $messageRetentionMapper;

	private MessageSnoozeMapper $messageSnoozeMapper;

	private ITimeFactory $timeFactory;

	private IConfig $config;

	public function __construct(MailAccountMapper $mailAccountMapper,
		AliasMapper $aliasMapper,
		MailboxMapper $mailboxMapper,
		MessageMapper $messageMapper,
		CollectedAddressMapper $collectedAddressMapper,
		TagMapper $tagMapper,
		MessageRetentionMapper $messageRetentionMapper,
		MessageSnoozeMapper $messageSnoozeMapper,
		ITimeFactory $timeFactory,
		private MessageBodyStorage $messageBodyStorage,
		private LocalAttachmentMapper $localAttachmentMapper,
		private AttachmentStorage $attachmentStorage,
		IConfig $config) {
		$this->aliasMapper = $aliasMapper;
		$this->mailboxMapper = $mailboxMapper;
		$this->messageMapper = $messageMapper;
		$this->collectedAddressMapper = $collectedAddressMapper;
		$this->tagMapper = $tagMapper;
		$this->messageRetentionMapper = $messageRetentionMapper;
		$this->messageSnoozeMapper = $messageSnoozeMapper;
		$this->mailAccountMapper = $mailAccountMapper;
		$this->timeFactory = $timeFactory;
		$this->config = $config;
	}

	public function cleanUp(LoggerInterface $logger): void {
		$task = (new PerformanceLogger(
			$this->timeFactory,
			$logger
		))->start('clean up');
		$this->mailAccountMapper->deleteProvisionedOrphanAccounts();
		$task->step('delete orphan provisioned accounts');
		$this->aliasMapper->deleteOrphans();
		$task->step('delete orphan aliases');
		$this->mailboxMapper->deleteOrphans();
		$task->step('delete orphan mailboxes');
		$this->messageMapper->deleteOrphans();
		$task->step('delete orphan messages');
		$this->collectedAddressMapper->deleteOrphans();
		$task->step('delete orphan collected addresses');
		$this->tagMapper->deleteOrphans();
		$task->step('delete orphan tags');
		$this->tagMapper->deleteDuplicates();
		$task->step('delete duplicate tags');
		$this->messageRetentionMapper->deleteOrphans();
		$task->step('delete expired messages');
		$this->messageSnoozeMapper->deleteOrphans();
		$task->step('delete orphan snoozes');
		$bodyResult = $this->cleanUpBodyCache();
		$logger->info('yoomail: body cache cleanup finished', $bodyResult);
		$task->step('clean message body cache');
		$attachmentResult = $this->cleanUpLocalAttachments();
		$logger->info('yoomail: local attachment cleanup finished', $attachmentResult);
		$task->step('clean local attachments');
		$task->end();
	}

	/**
	 * @return array<string, int|string>
	 */
	private function cleanUpBodyCache(): array {
		if (!$this->getBoolAppValue('body_cache_cleanup_enabled', true)) {
			return ['status' => 'disabled'];
		}

		$retentionDays = $this->getPositiveIntAppValue('body_cache_cleanup_days', self::DEFAULT_BODY_CACHE_RETENTION_DAYS);
		$sizeLimitMb = $this->getPositiveIntAppValue('body_cache_cleanup_size_mb', self::DEFAULT_BODY_CACHE_MAX_SIZE_MB);

		return [
			'status' => 'ok',
			'retentionDays' => $retentionDays,
			'sizeLimitMb' => $sizeLimitMb,
			...$this->messageBodyStorage->cleanUp($retentionDays * 86400, $sizeLimitMb * 1024 * 1024),
		];
	}

	/**
	 * @return array<string, int|string>
	 */
	private function cleanUpLocalAttachments(): array {
		if (!$this->getBoolAppValue('local_attachment_cleanup_enabled', true)) {
			return ['status' => 'disabled'];
		}

		$retentionDays = $this->getPositiveIntAppValue('local_attachment_cleanup_days', self::DEFAULT_LOCAL_ATTACHMENT_RETENTION_DAYS);
		$sizeLimitMb = $this->getPositiveIntAppValue('local_attachment_cleanup_size_mb', self::DEFAULT_LOCAL_ATTACHMENT_MAX_SIZE_MB);
		$createdBefore = $this->timeFactory->getTime() - ($retentionDays * 86400);

		$deletedFiles = 0;
		$deletedBytes = 0;

		foreach ($this->localAttachmentMapper->findUnattached($createdBefore) as $attachment) {
			$deletedBytes += $this->deleteUnattachedAttachment($attachment);
			$deletedFiles++;
		}

		$remainingAttachments = $this->localAttachmentMapper->findUnattached();
		$totalBytes = $this->sumAttachmentBytes($remainingAttachments);
		$sizeLimitBytes = $sizeLimitMb * 1024 * 1024;

		if ($sizeLimitBytes > 0 && $totalBytes > $sizeLimitBytes) {
			foreach ($remainingAttachments as $attachment) {
				if ($totalBytes <= $sizeLimitBytes) {
					break;
				}

				$size = $this->deleteUnattachedAttachment($attachment);
				$deletedFiles++;
				$deletedBytes += $size;
				$totalBytes -= $size;
			}
		}

		$finalAttachments = $this->localAttachmentMapper->findUnattached();

		return [
			'status' => 'ok',
			'retentionDays' => $retentionDays,
			'sizeLimitMb' => $sizeLimitMb,
			'deletedFiles' => $deletedFiles,
			'deletedBytes' => $deletedBytes,
			'remainingFiles' => count($finalAttachments),
			'remainingBytes' => $this->sumAttachmentBytes($finalAttachments),
		];
	}

	private function deleteUnattachedAttachment(LocalAttachment $attachment): int {
		$size = $this->attachmentStorage->getSize($attachment->getUserId(), $attachment->getId());
		$this->localAttachmentMapper->delete($attachment);
		$this->attachmentStorage->delete($attachment->getUserId(), $attachment->getId());
		return $size;
	}

	/**
	 * @param LocalAttachment[] $attachments
	 */
	private function sumAttachmentBytes(array $attachments): int {
		$total = 0;
		foreach ($attachments as $attachment) {
			$total += $this->attachmentStorage->getSize($attachment->getUserId(), $attachment->getId());
		}
		return $total;
	}

	private function getBoolAppValue(string $key, bool $default): bool {
		$fallback = $default ? 'yes' : 'no';
		return $this->config->getAppValue('yoomail', $key, $fallback) === 'yes';
	}

	private function getPositiveIntAppValue(string $key, int $default): int {
		$value = (int)$this->config->getAppValue('yoomail', $key, (string)$default);
		return $value > 0 ? $value : $default;
	}
}
