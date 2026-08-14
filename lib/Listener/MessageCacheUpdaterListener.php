<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Listener;

use OCA\YooMail\Db\MessageMapper;
use OCA\YooMail\Events\MessageDeletedEvent;
use OCA\YooMail\Events\MessageFlaggedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class MessageCacheUpdaterListener implements IEventListener {
	/** @var MessageMapper */
	private $mapper;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(MessageMapper $mapper,
		LoggerInterface $logger) {
		$this->mapper = $mapper;
		$this->logger = $logger;
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof MessageFlaggedEvent) {
			$messages = $this->mapper->findByUids($event->getMailbox(), [$event->getUid()]);
			$message = reset($messages);

			if ($message === false) {
				$this->logger->warning('Flagged message is not cached');
				return;
			}

			$message->setFlag($event->getFlag(), $event->isSet());

			$this->mapper->update($message);
		} elseif ($event instanceof MessageDeletedEvent) {
			$this->mapper->deleteByUid(
				$event->getMailbox(),
				$event->getMessageId()
			);
		}
	}
}
