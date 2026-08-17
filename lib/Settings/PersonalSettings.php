<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Settings;

use OCA\YooMail\Controller\NotificationSettingsController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;

class PersonalSettings implements IDelegatedSettings {
	public function __construct(
		private NotificationSettingsController $notificationSettingsController,
	) {
	}

	#[\Override]
	public function getName(): ?string {
		return null;
	}

	#[\Override]
	public function getAuthorizedAppConfig(): array {
		return [
			'yoomail' => [
				'/notification_.*/',
			],
		];
	}

	#[\Override]
	public function getForm(): TemplateResponse {
		return $this->notificationSettingsController->index();
	}

	#[\Override]
	public function getSection(): string {
		return 'yoomail';
	}

	#[\Override]
	public function getPriority(): int {
		return 61;
	}
}
