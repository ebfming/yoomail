<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Listener;

use OCA\YooMail\AppInfo\Application;
use OCA\YooMail\Service\NotificationSettingsService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;

/**
 * @template-implements IEventListener<Event|BeforeTemplateRenderedEvent>
 * @version b-2026.08.21
 */
class GlobalNotifierAssetsListener implements IEventListener {
	public function __construct(
		private IUserSession $userSession,
		private IInitialState $initialState,
		private NotificationSettingsService $notificationSettingsService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		if (!$event->isLoggedIn() || $event->getResponse()->getRenderAs() !== TemplateResponse::RENDER_AS_USER) {
			return;
		}

		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return;
		}

		$this->initialState->provideInitialState(
			'notification-settings',
			$this->notificationSettingsService->getUserSettings($user->getUID())
		);
		$this->initialState->provideInitialState(
			'notification-audio-urls',
			$this->notificationSettingsService->getAudioUrls()
		);

		// This listener must start before individual Nextcloud app bundles so it can
		// keep one realtime connection alive on every authenticated app page.
		Util::addInitScript(Application::APP_ID, 'yoomail-site-runtime-v4');
	}
}
