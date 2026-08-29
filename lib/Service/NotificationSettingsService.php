<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Service;

use OCA\YooMail\AppInfo\Application;
use OCP\IConfig;
use OCP\IURLGenerator;

class NotificationSettingsService {
	private const DEFAULTS = [
		'nativeNewMail' => '0',
		'soundEnabled' => '1',
		'toastEnabled' => '1',
		'topAppIconEnabled' => '1',
		'soundNewMail' => '1',
		'soundSendSuccess' => '1',
		'soundSendFail' => '1',
	];

	public function __construct(
		private IConfig $config,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getUserSettings(string $userId): array {
		return [
			'nativeNewMail' => $this->getSwitch($userId, 'notification_native_new_mail', self::DEFAULTS['nativeNewMail']),
			'soundEnabled' => $this->getSwitch($userId, 'notification_sound_enabled', self::DEFAULTS['soundEnabled']),
			'toastEnabled' => $this->getSwitch($userId, 'notification_toast_enabled', self::DEFAULTS['toastEnabled']),
			'topAppIconEnabled' => $this->getSwitch($userId, 'notification_top_app_icon_enabled', self::DEFAULTS['topAppIconEnabled']),
			'soundNewMail' => $this->getSwitch($userId, 'notification_sound_new_mail', self::DEFAULTS['soundNewMail']),
			'soundSendSuccess' => $this->getSwitch($userId, 'notification_sound_send_success', self::DEFAULTS['soundSendSuccess']),
			'soundSendFail' => $this->getSwitch($userId, 'notification_sound_send_fail', self::DEFAULTS['soundSendFail']),
		];
	}

	public function updateUserSettings(string $userId, array $settings): array {
		$values = [
			'nativeNewMail' => $this->readSwitchValue($settings, 'nativeNewMail', self::DEFAULTS['nativeNewMail']),
			'soundEnabled' => $this->readSwitchValue($settings, 'soundEnabled', self::DEFAULTS['soundEnabled']),
			'toastEnabled' => $this->readSwitchValue($settings, 'toastEnabled', self::DEFAULTS['toastEnabled']),
			'topAppIconEnabled' => $this->readSwitchValue($settings, 'topAppIconEnabled', self::DEFAULTS['topAppIconEnabled']),
			'soundNewMail' => $this->readSwitchValue($settings, 'soundNewMail', self::DEFAULTS['soundNewMail']),
			'soundSendSuccess' => $this->readSwitchValue($settings, 'soundSendSuccess', self::DEFAULTS['soundSendSuccess']),
			'soundSendFail' => $this->readSwitchValue($settings, 'soundSendFail', self::DEFAULTS['soundSendFail']),
		];

		$this->config->setUserValue($userId, Application::APP_ID, 'notification_native_new_mail', $values['nativeNewMail']);
		$this->config->setUserValue($userId, Application::APP_ID, 'notification_sound_enabled', $values['soundEnabled']);
		$this->config->setUserValue($userId, Application::APP_ID, 'notification_toast_enabled', $values['toastEnabled']);
		$this->config->setUserValue($userId, Application::APP_ID, 'notification_top_app_icon_enabled', $values['topAppIconEnabled']);
		$this->config->setUserValue($userId, Application::APP_ID, 'notification_sound_new_mail', $values['soundNewMail']);
		$this->config->setUserValue($userId, Application::APP_ID, 'notification_sound_send_success', $values['soundSendSuccess']);
		$this->config->setUserValue($userId, Application::APP_ID, 'notification_sound_send_fail', $values['soundSendFail']);

		return $this->getUserSettings($userId);
	}

	public function getAudioUrls(): array {
		return [
			'newMail' => $this->urlGenerator->linkTo(Application::APP_ID, 'img/sounds/push.wav'),
			'sendSuccess' => $this->urlGenerator->linkTo(Application::APP_ID, 'img/sounds/send-succeess.wav'),
			'sendFail' => $this->urlGenerator->linkTo(Application::APP_ID, 'img/sounds/send-fail.wav'),
		];
	}

	private function getSwitch(string $userId, string $key, string $default): bool {
		return $this->normalizeSwitch($this->config->getUserValue($userId, Application::APP_ID, $key, $default));
	}

	private function readSwitchValue(array $settings, string $key, string $default): string {
		return $this->normalizeSwitch($settings[$key] ?? $default) ? '1' : '0';
	}

	/**
	 * @param mixed $value
	 */
	private function normalizeSwitch($value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value === 1;
		}

		$value = strtolower(trim((string)$value));
		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}
}
