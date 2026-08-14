<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Service\Classification;

use OCA\YooMail\Account;
use OCA\YooMail\Db\Mailbox;
use OCA\YooMail\Db\Message;
use OCA\YooMail\Service\Classification\FeatureExtraction\ImportantMessagesExtractor;
use OCA\YooMail\Service\Classification\FeatureExtraction\ReadMessagesExtractor;
use OCA\YooMail\Service\Classification\FeatureExtraction\RepliedMessagesExtractor;
use OCA\YooMail\Service\Classification\FeatureExtraction\SentMessagesExtractor;
use function array_combine;
use function array_map;

class ImportanceRulesClassifier {
	/** @var ImportantMessagesExtractor */
	private $importantMessagesExtractor;

	/** @var ReadMessagesExtractor */
	private $readMessagesExtractor;

	/** @var RepliedMessagesExtractor */
	private $repliedMessagesExtractor;

	/** @var SentMessagesExtractor */
	private $sentMessagesExtractor;

	public function __construct(ImportantMessagesExtractor $importantMessagesExtractor,
		ReadMessagesExtractor $readMessagesExtractor,
		RepliedMessagesExtractor $repliedMessagesExtractor,
		SentMessagesExtractor $sentMessagesExtractor) {
		$this->importantMessagesExtractor = $importantMessagesExtractor;
		$this->readMessagesExtractor = $readMessagesExtractor;
		$this->repliedMessagesExtractor = $repliedMessagesExtractor;
		$this->sentMessagesExtractor = $sentMessagesExtractor;
	}

	/**
	 * @param Account $account
	 * @param Mailbox[] $incomingMailboxes
	 * @param Mailbox[] $outgoingMailboxes
	 * @param Message[] $messages
	 *
	 * @return bool[]
	 */
	public function classifyImportance(Account $account,
		array $incomingMailboxes,
		array $outgoingMailboxes,
		array $messages): array {
		$this->importantMessagesExtractor->prepare($account, $incomingMailboxes, $outgoingMailboxes, $messages);
		$this->readMessagesExtractor->prepare($account, $incomingMailboxes, $outgoingMailboxes, $messages);
		$this->repliedMessagesExtractor->prepare($account, $incomingMailboxes, $outgoingMailboxes, $messages);
		$this->sentMessagesExtractor->prepare($account, $incomingMailboxes, $outgoingMailboxes, $messages);

		return array_combine(
			array_map(static fn (Message $m) => $m->getUid(), $messages),
			array_map(function (Message $m) {
				$from = $m->getFrom()->first();
				if ($from === null || $from->getEmail() === null) {
					return false;
				}

				return $this->importantMessagesExtractor->extract($m) > 0.3
					|| $this->readMessagesExtractor->extract($m) > 0.7
					|| $this->repliedMessagesExtractor->extract($m) > 0.1
					|| $this->sentMessagesExtractor->extract($m) > 0.1;
			}, $messages)
		);
	}
}
