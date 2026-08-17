<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Controller;

use OCA\YooMail\AppInfo\Application;
use OCA\YooMail\Exception\ValidationException;
use OCA\YooMail\Http\JsonResponse as HttpJsonResponse;
use OCA\YooMail\Service\AntiSpamService;
use OCA\YooMail\Service\Classification\ClassificationSettingsService;
use OCA\YooMail\Service\Provisioning\Manager as ProvisioningManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Container\ContainerInterface;

use function array_merge;
use function filter_var;
use function fsockopen;
use function in_array;
use function preg_match;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class SettingsController extends Controller {
	private const TIME_FORMATS = ['12', '24'];
	private const REALTIME_MODES = ['websocket', 'http'];
	private const FETCH_RANGE_OPTIONS = ['7', '30', '90', '180', 'all'];

	private ProvisioningManager $provisioningManager;
	private AntiSpamService $antiSpamService;
	private ContainerInterface $container;
	private IConfig $config;

	public function __construct(
		IRequest $request,
		ProvisioningManager $provisioningManager,
		AntiSpamService $antiSpamService,
		IConfig $config,
		ContainerInterface $container,
		private IL10N $l10n,
		private ClassificationSettingsService $classificationSettingsService,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->provisioningManager = $provisioningManager;
		$this->antiSpamService = $antiSpamService;
		$this->config = $config;
		$this->container = $container;
	}

	public function index(): JSONResponse {
		$provisionings = $this->provisioningManager->getConfigs();
		return new JSONResponse($provisionings);
	}

	public function provision() : JSONResponse {
		$count = $this->provisioningManager->provision();
		return new JSONResponse(['count' => $count]);
	}

	public function createProvisioning(array $data): JSONResponse {
		try {
			return new JSONResponse(
				$this->provisioningManager->newProvisioning($data)
			);
		} catch (ValidationException $e) {
			return HttpJsonResponse::fail([$e->getFields()]);
		} catch (\Exception $e) {
			return HttpJsonResponse::fail([$e->getMessage()]);
		}
	}

	public function updateProvisioning(int $id, array $data): JSONResponse {
		try {
			$this->provisioningManager->updateProvisioning(array_merge($data, ['id' => $id]));
		} catch (ValidationException $e) {
			return HttpJsonResponse::fail([$e->getFields()]);
		} catch (\Exception $e) {
			return HttpJsonResponse::fail([$e->getMessage()]);
		}

		return new JSONResponse([]);
	}

	public function deprovision(int $id): JSONResponse {
		$provisioning = $this->provisioningManager->getConfigById($id);

		if ($provisioning !== null) {
			$this->provisioningManager->deprovision($provisioning);
		}

		return new JSONResponse([]);
	}

	/**
	 *
	 * @return JSONResponse
	 */
	public function setAntiSpamEmail(string $spam, string $ham): JSONResponse {
		$this->antiSpamService->setSpamEmail($spam);
		$this->antiSpamService->setHamEmail($ham);
		return new JSONResponse([]);
	}

	/**
	 * Store the credentials used for SMTP in the config
	 *
	 * @return JSONResponse
	 */
	public function deleteAntiSpamEmail(): JSONResponse {
		$this->antiSpamService->deleteConfig();
		return new JSONResponse([]);
	}

	public function setAllowNewMailAccounts(bool $allowed): void {
		$this->config->setAppValue('yoomail', 'allow_new_mail_accounts', $allowed ? 'yes' : 'no');
	}

	public function setEnabledLlmProcessing(bool $enabled): JSONResponse {
		$this->config->setAppValue('yoomail', 'llm_processing', $enabled ? 'yes' : 'no');
		return new JSONResponse([]);
	}
	public function setLayoutMessageView(string $value): JSONResponse {
		$this->config->setAppValue('yoomail', 'layout_message_view', $value);
		return new JSONResponse([]);
	}
	public function setImportanceClassificationEnabledByDefault(bool $enabledByDefault): JSONResponse {
		$this->classificationSettingsService->setClassificationEnabledByDefault($enabledByDefault);
		return new JSONResponse([]);
	}

	public function getBasicSettings(): JSONResponse {
		return new JSONResponse($this->readBasicSettings());
	}

	public function updateBasicSettings(
	): JSONResponse {
		$timeFormatDefault = (string)$this->request->getParam('timeFormatDefault', '24');
		$realtimeMode = (string)$this->request->getParam('realtimeMode', 'websocket');
		$deleteSyncLocalToServer = $this->toBool($this->request->getParam('deleteSyncLocalToServer', true));
		$deleteSyncServerToLocal = $this->toBool($this->request->getParam('deleteSyncServerToLocal', true));
		$fetchRangeDays = (string)$this->request->getParam('fetchRangeDays', '30');
		$bodyCacheCleanupEnabled = $this->toBool($this->request->getParam('bodyCacheCleanupEnabled', true));
		$bodyCacheCleanupDays = (int)$this->request->getParam('bodyCacheCleanupDays', 30);
		$bodyCacheCleanupSizeMb = (int)$this->request->getParam('bodyCacheCleanupSizeMb', 1024);
		$localAttachmentCleanupEnabled = $this->toBool($this->request->getParam('localAttachmentCleanupEnabled', true));
		$localAttachmentCleanupDays = (int)$this->request->getParam('localAttachmentCleanupDays', 30);
		$localAttachmentCleanupSizeMb = (int)$this->request->getParam('localAttachmentCleanupSizeMb', 512);

		if (!in_array($timeFormatDefault, self::TIME_FORMATS, true)) {
			return HttpJsonResponse::fail([$this->l10n->t('Unsupported time format')], 400);
		}

		if (!in_array($realtimeMode, self::REALTIME_MODES, true)) {
			return HttpJsonResponse::fail([$this->l10n->t('Unsupported mail sync mode')], 400);
		}

		if (!in_array($fetchRangeDays, self::FETCH_RANGE_OPTIONS, true)) {
			return HttpJsonResponse::fail([$this->l10n->t('Unsupported fetch range')], 400);
		}

		if ($bodyCacheCleanupDays <= 0 || $bodyCacheCleanupSizeMb <= 0 || $localAttachmentCleanupDays <= 0 || $localAttachmentCleanupSizeMb <= 0) {
			return HttpJsonResponse::fail([$this->l10n->t('Cleanup values must be positive numbers')], 400);
		}

		$this->config->setAppValue(Application::APP_ID, 'time_format_default', $timeFormatDefault);
		$this->config->setAppValue(Application::APP_ID, 'realtime_mode', $realtimeMode);
		$this->config->setAppValue(Application::APP_ID, 'delete_sync_local_to_server', $deleteSyncLocalToServer ? 'yes' : 'no');
		$this->config->setAppValue(Application::APP_ID, 'delete_sync_server_to_local', $deleteSyncServerToLocal ? 'yes' : 'no');
		$this->config->setAppValue(Application::APP_ID, 'fetch_range_days', $fetchRangeDays);
		$this->config->setAppValue(Application::APP_ID, 'body_cache_cleanup_enabled', $bodyCacheCleanupEnabled ? 'yes' : 'no');
		$this->config->setAppValue(Application::APP_ID, 'body_cache_cleanup_days', (string)$bodyCacheCleanupDays);
		$this->config->setAppValue(Application::APP_ID, 'body_cache_cleanup_size_mb', (string)$bodyCacheCleanupSizeMb);
		$this->config->setAppValue(Application::APP_ID, 'local_attachment_cleanup_enabled', $localAttachmentCleanupEnabled ? 'yes' : 'no');
		$this->config->setAppValue(Application::APP_ID, 'local_attachment_cleanup_days', (string)$localAttachmentCleanupDays);
		$this->config->setAppValue(Application::APP_ID, 'local_attachment_cleanup_size_mb', (string)$localAttachmentCleanupSizeMb);

		return new JSONResponse($this->readBasicSettings());
	}

	public function getRealtimeHealth(): JSONResponse {
		$settings = $this->readBasicSettings();
		$mode = $settings['realtimeMode'];
		$host = $settings['wsHost'];
		$port = (int)$settings['wsPort'];
		$publicUrl = $settings['wsPublicUrl'];

		$issues = [];
		$status = 'ok';
		$reachable = false;

		if ($mode !== 'websocket') {
			$status = 'warning';
			$issues[] = $this->l10n->t('Realtime mode is disabled. YooMail will use classic HTTP sync.');
		} else {
			$reachable = $this->canConnectToSocket($host, $port);
			if (!$reachable) {
				$status = 'error';
				$issues[] = $this->l10n->t('Cannot connect to the realtime socket at %1$s:%2$s.', [$host, (string)$port]);
			}

			if ($publicUrl !== '' && filter_var($publicUrl, FILTER_VALIDATE_URL) === false) {
				$status = 'error';
				$issues[] = $this->l10n->t('The public WebSocket URL is invalid.');
			}

			if ($publicUrl !== '' && !preg_match('/^wss?:\\/\\//', $publicUrl)) {
				$status = 'error';
				$issues[] = $this->l10n->t('The public WebSocket URL must start with ws:// or wss://.');
			}
		}

		return new JSONResponse([
			'status' => $status,
			'mode' => $mode,
			'reachable' => $reachable,
			'host' => $host,
			'port' => $port,
			'publicUrl' => $publicUrl,
			'issues' => $issues,
			'summary' => $this->buildHealthSummary($status, $mode, $host, $port),
		]);
	}

	private function readBasicSettings(): array {
		return [
			'timeFormatDefault' => $this->config->getAppValue(Application::APP_ID, 'time_format_default', '24'),
			'realtimeMode' => $this->config->getAppValue(Application::APP_ID, 'realtime_mode', 'websocket'),
			'deleteSyncLocalToServer' => $this->config->getAppValue(Application::APP_ID, 'delete_sync_local_to_server', 'yes') === 'yes',
			'deleteSyncServerToLocal' => $this->config->getAppValue(Application::APP_ID, 'delete_sync_server_to_local', 'yes') === 'yes',
			'fetchRangeDays' => $this->config->getAppValue(Application::APP_ID, 'fetch_range_days', '30'),
			'bodyCacheCleanupEnabled' => $this->config->getAppValue(Application::APP_ID, 'body_cache_cleanup_enabled', 'yes') === 'yes',
			'bodyCacheCleanupDays' => (int)$this->config->getAppValue(Application::APP_ID, 'body_cache_cleanup_days', '30'),
			'bodyCacheCleanupSizeMb' => (int)$this->config->getAppValue(Application::APP_ID, 'body_cache_cleanup_size_mb', '1024'),
			'localAttachmentCleanupEnabled' => $this->config->getAppValue(Application::APP_ID, 'local_attachment_cleanup_enabled', 'yes') === 'yes',
			'localAttachmentCleanupDays' => (int)$this->config->getAppValue(Application::APP_ID, 'local_attachment_cleanup_days', '30'),
			'localAttachmentCleanupSizeMb' => (int)$this->config->getAppValue(Application::APP_ID, 'local_attachment_cleanup_size_mb', '512'),
			'wsHost' => $this->config->getAppValue(Application::APP_ID, 'realtime_ws_host', '127.0.0.1'),
			'wsPort' => (int)$this->config->getAppValue(Application::APP_ID, 'realtime_ws_port', '8789'),
			'wsPublicUrl' => $this->config->getAppValue(Application::APP_ID, 'realtime_ws_public_url', ''),
		];
	}

	private function canConnectToSocket(string $host, int $port): bool {
		$errno = 0;
		$errstr = '';
		$socket = @fsockopen($host, $port, $errno, $errstr, 2.0);
		if ($socket === false) {
			return false;
		}

		fclose($socket);
		return true;
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_string($value)) {
			return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
		}

		if (is_int($value)) {
			return $value === 1;
		}

		return false;
	}

	private function buildHealthSummary(string $status, string $mode, string $host, int $port): string {
		if ($mode !== 'websocket') {
			return $this->l10n->t('Realtime sync is disabled. HTTP sync is active.');
		}

		if ($status === 'ok') {
			return $this->l10n->t('Realtime socket is reachable at %1$s:%2$s.', [$host, (string)$port]);
		}

		return $this->l10n->t('Realtime socket check failed for %1$s:%2$s.', [$host, (string)$port]);
	}

}
