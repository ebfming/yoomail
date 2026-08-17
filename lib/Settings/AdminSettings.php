<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Settings;

use OCA\YooMail\AppInfo\Application;
use OCA\YooMail\Integration\GoogleIntegration;
use OCA\YooMail\Integration\MicrosoftIntegration;
use OCA\YooMail\Service\AiIntegrations\AiIntegrationsService;
use OCA\YooMail\Service\AntiSpamService;
use OCA\YooMail\Service\Classification\ClassificationSettingsService;
use OCA\YooMail\Service\Provisioning\Manager as ProvisioningManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Defaults;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\Settings\ISettings;
use OCP\TaskProcessing\TaskTypes\TextToText;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;

class AdminSettings implements ISettings {
	/** @var IInitialStateService */
	private $initialStateService;

	/** @var ProvisioningManager */
	private $provisioningManager;

	/** @var AntiSpamService */
	private $antiSpamService;

	private GoogleIntegration $googleIntegration;
	private MicrosoftIntegration $microsoftIntegration;
	private IConfig $config;
	private AiIntegrationsService $aiIntegrationsService;

	public function __construct(
		IInitialStateService $initialStateService,
		ProvisioningManager $provisioningManager,
		AntiSpamService $antiSpamService,
		GoogleIntegration $googleIntegration,
		MicrosoftIntegration $microsoftIntegration,
		IConfig $config,
		AiIntegrationsService $aiIntegrationsService,
		private Defaults $themingDefaults,
		private ClassificationSettingsService $classificationSettingsService,
	) {
		$this->initialStateService = $initialStateService;
		$this->provisioningManager = $provisioningManager;
		$this->antiSpamService = $antiSpamService;
		$this->googleIntegration = $googleIntegration;
		$this->microsoftIntegration = $microsoftIntegration;
		$this->config = $config;
		$this->aiIntegrationsService = $aiIntegrationsService;
	}

	#[\Override]
	public function getForm() {
		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'provisioning_settings',
			$this->provisioningManager->getConfigs()
		);

		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'antispam_setting',
			[
				'spam' => $this->antiSpamService->getSpamEmail(),
				'ham' => $this->antiSpamService->getHamEmail(),
			]
		);

		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'allow_new_mail_accounts',
			$this->config->getAppValue('yoomail', 'allow_new_mail_accounts', 'yes') === 'yes'
		);

		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'layout_message_view',
			$this->config->getAppValue('yoomail', 'layout_message_view', 'threaded')
		);

		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'admin_basic_settings',
			[
				'timeFormatDefault' => $this->config->getAppValue('yoomail', 'time_format_default', '24'),
				'realtimeMode' => $this->config->getAppValue('yoomail', 'realtime_mode', 'websocket'),
				'deleteSyncLocalToServer' => $this->config->getAppValue('yoomail', 'delete_sync_local_to_server', 'yes') === 'yes',
				'deleteSyncServerToLocal' => $this->config->getAppValue('yoomail', 'delete_sync_server_to_local', 'yes') === 'yes',
				'fetchRangeDays' => $this->config->getAppValue('yoomail', 'fetch_range_days', '30'),
				'bodyCacheCleanupEnabled' => $this->config->getAppValue('yoomail', 'body_cache_cleanup_enabled', 'yes') === 'yes',
				'bodyCacheCleanupDays' => (int)$this->config->getAppValue('yoomail', 'body_cache_cleanup_days', '30'),
				'bodyCacheCleanupSizeMb' => (int)$this->config->getAppValue('yoomail', 'body_cache_cleanup_size_mb', '1024'),
				'localAttachmentCleanupEnabled' => $this->config->getAppValue('yoomail', 'local_attachment_cleanup_enabled', 'yes') === 'yes',
				'localAttachmentCleanupDays' => (int)$this->config->getAppValue('yoomail', 'local_attachment_cleanup_days', '30'),
				'localAttachmentCleanupSizeMb' => (int)$this->config->getAppValue('yoomail', 'local_attachment_cleanup_size_mb', '512'),
				'wsHost' => $this->config->getAppValue('yoomail', 'realtime_ws_host', '127.0.0.1'),
				'wsPort' => (int)$this->config->getAppValue('yoomail', 'realtime_ws_port', '8789'),
				'wsPublicUrl' => $this->config->getAppValue('yoomail', 'realtime_ws_public_url', ''),
			]
		);

		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'llm_processing',
			$this->aiIntegrationsService->isLlmProcessingEnabled(),
		);

		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'enabled_llm_free_prompt_backend',
			$this->aiIntegrationsService->isLlmAvailable(TextToText::ID)
		);

		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'enabled_llm_summary_backend',
			$this->aiIntegrationsService->isLlmAvailable(TextToTextSummary::ID)
		);

			$this->initialStateService->provideInitialState(
				Application::APP_ID,
				'google_oauth_client_id',
				$this->googleIntegration->getClientId() ?? '',
			);
		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'google_oauth_redirect_url',
			$this->googleIntegration->getRedirectUrl(),
		);
		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'importance_classification_default',
			$this->classificationSettingsService->isClassificationEnabledByDefault(),
		);
		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'microsoft_oauth_tenant_id',
			$this->microsoftIntegration->getTenantId(),
		);
			$this->initialStateService->provideInitialState(
				Application::APP_ID,
				'microsoft_oauth_client_id',
				$this->microsoftIntegration->getClientId() ?? '',
			);
		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'microsoft_oauth_redirect_url',
			$this->microsoftIntegration->getRedirectUrl(),
		);
		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'microsoft_oauth_docs',
			$this->themingDefaults->buildDocLinkToKey('admin-groupware-oauth-microsoft'),
		);

		return new TemplateResponse(Application::APP_ID, 'settings-admin');
	}

	#[\Override]
	public function getSection() {
		return 'yoomail';
	}

	#[\Override]
	public function getPriority() {
		return 90;
	}
}
