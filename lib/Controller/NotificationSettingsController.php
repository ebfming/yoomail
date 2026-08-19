<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Controller;

use OCA\YooMail\AppInfo\Application;
use OCA\YooMail\Service\NotificationSettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\Util;

class NotificationSettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private ?string $userId,
		private NotificationSettingsService $notificationSettingsService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'personal-notification-settings');
		Util::addStyle(Application::APP_ID, 'personal-notification-settings');

		return new TemplateResponse(
			Application::APP_ID,
			'settings-personal',
			[
				'notificationData' => [
					'settings' => $this->userId !== null
						? $this->notificationSettingsService->getUserSettings($this->userId)
						: [],
					'audioUrls' => $this->notificationSettingsService->getAudioUrls(),
				],
			],
			TemplateResponse::RENDER_AS_BLANK
		);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getSettings(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['message' => 'Not authenticated'], 401);
		}

		return new JSONResponse($this->notificationSettingsService->getUserSettings($this->userId));
	}

	#[NoAdminRequired]
	public function updateSettings(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['message' => 'Not authenticated'], 401);
		}

		$data = $this->readJsonOrRequestParams();

		return new JSONResponse(
			$this->notificationSettingsService->updateUserSettings($this->userId, $data)
		);
	}

	private function readJsonOrRequestParams(): array {
		$putPayload = $this->request->put;
		if (is_array($putPayload)) {
			return $putPayload;
		}

		return $this->request->getParams();
	}
}
