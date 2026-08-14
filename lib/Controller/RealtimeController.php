<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Controller;

use OCA\YooMail\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

/**
 * Issues a short-lived HMAC-signed token that the frontend uses to
 * authenticate its WebSocket connection to the realtime service.
 *
 * The token is stateless: it encodes the user id, an expiry timestamp and
 * an HMAC-SHA256 signature computed with the Nextcloud secret. The realtime
 * service validates it with the same secret, so no shared cache is needed.
 *
 * Token format: base64url(userId).base64url(expiry).base64url(hmac)
 */
class RealtimeController extends Controller {
	private IConfig $config;
	private ?string $userId;

	public function __construct(
		IRequest $request,
		IConfig $config,
		?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->config = $config;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 */
	public function token(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'not authenticated'], 401);
		}

		$ttl = 60;
		$expiry = time() + $ttl;
		$secret = $this->config->getSystemValueString('secret');
		$data = $this->userId . '.' . $expiry;
		$sig = hash_hmac('sha256', $data, $secret);
		$token = rtrim(base64_encode($this->userId), '=') . '.'
			. rtrim(base64_encode((string)$expiry), '=') . '.'
			. rtrim(base64_encode($sig), '=');

		// Derive the WebSocket URL from the current request's scheme/host,
		// or fall back to the configured public URL.
		$publicUrl = $this->config->getAppValue('yoomail', 'realtime_ws_public_url', '');
		if ($publicUrl === '') {
			$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'wss' : 'ws';
			$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
			$wsUrl = "{$scheme}://{$host}/yoomail-ws";
		} else {
			$wsUrl = $publicUrl;
		}

		return new JSONResponse([
			'token' => $token,
			'wsUrl' => $wsUrl,
		]);
	}
}
