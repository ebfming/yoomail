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
		'nativeNewMail' => false,
		'soundEnabled' => true,
		'toastEnabled' => true,
		'soundNewMail' => true,
		'soundSendSuccess' => true,
		'soundSendFail' => true,
	];

	public function __construct(
		private IConfig $config,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getUserSettings(string $userId): array {
		return [
			'nativeNewMail' => $this->getBool($userId, 'notification_native_new_mail', self::DEFAULTS['nativeNewMail']),
			'soundEnabled' => $this->getBool($userId, 'notification_sound_enabled', self::DEFAULTS['soundEnabled']),
			'toastEnabled' => $this->getBool($userId, 'notification_toast_enabled', self::DEFAULTS['toastEnabled']),
			'soundNewMail' => $this->getBool($userId, 'notification_sound_new_mail', self::DEFAULTS['soundNewMail']),
			'soundSendSuccess' => $this->getBool($userId, 'notification_sound_send_success', self::DEFAULTS['soundSendSuccess']),
			'soundSendFail' => $this->getBool($userId, 'notification_sound_send_fail', self::DEFAULTS['soundSendFail']),
		];
	}

	public function updateUserSettings(string $userId, array $settings): array {
		$normalized = [
			'nativeNewMail' => (bool)($settings['nativeNewMail'] ?? self::DEFAULTS['nativeNewMail']),
			'soundEnabled' => (bool)($settings['soundEnabled'] ?? self::DEFAULTS['soundEnabled']),
			'toastEnabled' => (bool)($settings['toastEnabled'] ?? self::DEFAULTS['toastEnabled']),
			'soundNewMail' => (bool)($settings['soundNewMail'] ?? self::DEFAULTS['soundNewMail']),
			'soundSendSuccess' => (bool)($settings['soundSendSuccess'] ?? self::DEFAULTS['soundSendSuccess']),
			'soundSendFail' => (bool)($settings['soundSendFail'] ?? self::DEFAULTS['soundSendFail']),
		];

		$this->setBool($userId, 'notification_native_new_mail', $normalized['nativeNewMail']);
		$this->setBool($userId, 'notification_sound_enabled', $normalized['soundEnabled']);
		$this->setBool($userId, 'notification_toast_enabled', $normalized['toastEnabled']);
		$this->setBool($userId, 'notification_sound_new_mail', $normalized['soundNewMail']);
		$this->setBool($userId, 'notification_sound_send_success', $normalized['soundSendSuccess']);
		$this->setBool($userId, 'notification_sound_send_fail', $normalized['soundSendFail']);

		return $normalized;
	}

	public function getAudioUrls(): array {
		return [
			'newMail' => $this->urlGenerator->linkTo(Application::APP_ID, 'img/sounds/push.wav'),
			'sendSuccess' => $this->urlGenerator->linkTo(Application::APP_ID, 'img/sounds/send-succeess.wav'),
			'sendFail' => $this->urlGenerator->linkTo(Application::APP_ID, 'img/sounds/send-fail.wav'),
		];
	}

	private function getBool(string $userId, string $key, bool $default): bool {
		$fallback = $default ? 'yes' : 'no';
		return $this->config->getUserValue($userId, Application::APP_ID, $key, $fallback) === 'yes';
	}

	private function setBool(string $userId, string $key, bool $value): void {
		$this->config->setUserValue($userId, Application::APP_ID, $key, $value ? 'yes' : 'no');
	}
}
