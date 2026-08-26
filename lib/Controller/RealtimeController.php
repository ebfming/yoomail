<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Controller;

use OCA\YooMail\AppInfo\Application;
use OCA\YooMail\Service\RealtimeAuthService;
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
 * @version b-2026.08.21
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

		// Mode switch: in 'http' mode the realtime service is disabled, so
		// the frontend gets no token and never opens a WebSocket connection
		// (it falls back to the classic polling logic).
		$mode = $this->config->getAppValue('yoomail', 'realtime_mode', 'websocket');
		if ($mode !== 'websocket') {
			return new JSONResponse(['error' => 'realtime disabled'], 503);
		}

		$token = (new RealtimeAuthService($this->config))->issueToken($this->userId);

		// Derive the WebSocket URL from the current request's scheme/host,
		// or fall back to the configured public URL.
		$publicUrl = $this->config->getAppValue('yoomail', 'realtime_ws_public_url', '');
		if ($publicUrl === '') {
			$scheme = $this->request->getServerProtocol() === 'https' ? 'wss' : 'ws';
			$host = $this->request->getServerHost();
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
