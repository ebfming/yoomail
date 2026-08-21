<?php

declare(strict_types=1);

namespace OCA\YooMail\Service\Sync;

/**
 * Immutable result of a mailbox sync run.
 *
 * Carries the lists of new/changed/vanished messages produced by one sync
 * pass so the caller can apply them to the database cache in one go.
 *
 * @version b-2026.08.21
 */
final class MailboxSyncDelta {
	/**
	 * @param int[] $newUids
	 * @param int[] $changedUids
	 * @param int[] $vanishedIds
	 */
	public function __construct(
		private bool $rebuildThreads = true,
		private bool $initialSync = false,
		private array $newUids = [],
		private array $changedUids = [],
		private array $vanishedIds = [],
	) {
	}

	public static function initialSync(): self {
		return new self(true, true);
	}

	/**
	 * @param int[] $newUids
	 * @param int[] $changedUids
	 * @param int[] $vanishedIds
	 */
	public static function partial(bool $rebuildThreads, array $newUids, array $changedUids, array $vanishedIds): self {
		return new self($rebuildThreads, false, $newUids, $changedUids, $vanishedIds);
	}

	public function shouldRebuildThreads(): bool {
		return $this->rebuildThreads;
	}

	public function isInitialSync(): bool {
		return $this->initialSync;
	}

	/**
	 * @return int[]
	 */
	public function getNewUids(): array {
		return $this->newUids;
	}

	/**
	 * @return int[]
	 */
	public function getChangedUids(): array {
		return $this->changedUids;
	}

	/**
	 * @return int[]
	 */
	public function getVanishedIds(): array {
		return $this->vanishedIds;
	}
}
