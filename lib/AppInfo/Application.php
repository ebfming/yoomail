<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2014-2016 owncloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\YooMail\AppInfo;

use Horde_Translation;
use OCA\YooMail\ContextChat\ContextChatProvider;
use OCA\YooMail\Contracts\IAttachmentService;
use OCA\YooMail\Contracts\IAvatarService;
use OCA\YooMail\Contracts\IDkimService;
use OCA\YooMail\Contracts\IDkimValidator;
use OCA\YooMail\Contracts\IMailManager;
use OCA\YooMail\Contracts\IMailSearch;
use OCA\YooMail\Contracts\IMailTransmission;
use OCA\YooMail\Contracts\ITrustedSenderService;
use OCA\YooMail\Contracts\IUserPreferences;
use OCA\YooMail\Dashboard\ImportantMailWidget;
use OCA\YooMail\Dashboard\UnreadMailWidget;
use OCA\YooMail\Events\BeforeImapClientCreated;
use OCA\YooMail\Events\DraftMessageCreatedEvent;
use OCA\YooMail\Events\DraftSavedEvent;
use OCA\YooMail\Events\MailboxesSynchronizedEvent;
use OCA\YooMail\Events\MessageDeletedEvent;
use OCA\YooMail\Events\MessageFlaggedEvent;
use OCA\YooMail\Events\MessageSentEvent;
use OCA\YooMail\Events\NewMessagesSynchronized;
use OCA\YooMail\Events\OutboxMessageCreatedEvent;
use OCA\YooMail\Events\SynchronizationEvent;
use OCA\YooMail\HordeTranslationHandler;
use OCA\YooMail\Http\Middleware\ErrorMiddleware;
use OCA\YooMail\Http\Middleware\ProvisioningMiddleware;
use OCA\YooMail\Listener\AccountSynchronizedThreadUpdaterListener;
use OCA\YooMail\Listener\AddressCollectionListener;
use OCA\YooMail\Listener\DeleteDraftListener;
use OCA\YooMail\Listener\FollowUpClassifierListener;
use OCA\YooMail\Listener\HamReportListener;
use OCA\YooMail\Listener\InteractionListener;
use OCA\YooMail\Listener\MailboxesSynchronizedSpecialMailboxesUpdater;
use OCA\YooMail\Listener\MessageCacheUpdaterListener;
use OCA\YooMail\Listener\MessageKnownSinceListener;
use OCA\YooMail\Listener\MoveJunkListener;
use OCA\YooMail\Listener\NewMessagesNotifier;
use OCA\YooMail\Listener\NewMessagesSummarizeListener;
use OCA\YooMail\Listener\OauthTokenRefreshListener;
use OCA\YooMail\Listener\OptionalIndicesListener;
use OCA\YooMail\Listener\OutOfOfficeListener;
use OCA\YooMail\Listener\SpamReportListener;
use OCA\YooMail\Listener\TaskProcessingListener;
use OCA\YooMail\Listener\UserDeletedListener;
use OCA\YooMail\Notification\Notifier;
use OCA\YooMail\Provider\MailProvider;
use OCA\YooMail\Search\FilteringProvider;
use OCA\YooMail\Service\Attachment\AttachmentService;
use OCA\YooMail\Service\Avatar\FaviconDataAccess;
use OCA\YooMail\Service\AvatarService;
use OCA\YooMail\Service\DkimService;
use OCA\YooMail\Service\DkimValidator;
use OCA\YooMail\Service\MailManager;
use OCA\YooMail\Service\MailTransmission;
use OCA\YooMail\Service\Search\MailSearch;
use OCA\YooMail\Service\TrustedSenderService;
use OCA\YooMail\Service\UserPreferenceService;
use OCA\YooMail\SetupChecks\MailConnectionPerformance;
use OCA\YooMail\SetupChecks\MailTransport;
use OCA\YooMail\UserMigration\MailAccountMigrator;
use OCA\YooMail\Vendor\Favicon\Favicon;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\ContextChat\Events\ContentProviderRegisterEvent;
use OCP\DB\Events\AddMissingIndicesEvent;
use OCP\IServerContainer;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use OCP\User\Events\OutOfOfficeChangedEvent;
use OCP\User\Events\OutOfOfficeClearedEvent;
use OCP\User\Events\OutOfOfficeEndedEvent;
use OCP\User\Events\OutOfOfficeScheduledEvent;
use OCP\User\Events\OutOfOfficeStartedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCP\Util;
use Psr\Container\ContainerInterface;

include_once __DIR__ . '/../../vendor/autoload.php';
include_once __DIR__ . '/../functions.php';

/**
 * @codeCoverageIgnore
 */
final class Application extends App implements IBootstrap {
	public const APP_ID = 'yoomail';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	#[\Override]
	public function register(IRegistrationContext $context): void {
		$context->registerParameter('hostname', Util::getServerHostName());

		$context->registerService('userFolder', static function (ContainerInterface $c) {
			$userContainer = $c->get(IServerContainer::class);
			$uid = $c->get('userId');

			return $userContainer->getUserFolder($uid);
		});
		$context->registerService(Favicon::class, function (ContainerInterface $c) {
			$favicon = new Favicon();
			$favicon->setDataAccess(
				$c->get(FaviconDataAccess::class),
			);
			return $favicon;
		});

		$context->registerServiceAlias(IAvatarService::class, AvatarService::class);
		$context->registerServiceAlias(IAttachmentService::class, AttachmentService::class);
		$context->registerServiceAlias(IMailManager::class, MailManager::class);
		$context->registerServiceAlias(IMailSearch::class, MailSearch::class);
		$context->registerServiceAlias(IMailTransmission::class, MailTransmission::class);
		$context->registerServiceAlias(ITrustedSenderService::class, TrustedSenderService::class);
		$context->registerServiceAlias(IUserPreferences::class, UserPreferenceService::class);
		$context->registerServiceAlias(IDkimService::class, DkimService::class);
		$context->registerServiceAlias(IDkimValidator::class, DkimValidator::class);

		$context->registerEventListener(AddMissingIndicesEvent::class, OptionalIndicesListener::class);
		$context->registerEventListener(BeforeImapClientCreated::class, OauthTokenRefreshListener::class);
		$context->registerEventListener(DraftSavedEvent::class, DeleteDraftListener::class);
		$context->registerEventListener(DraftMessageCreatedEvent::class, DeleteDraftListener::class);
		$context->registerEventListener(OutboxMessageCreatedEvent::class, DeleteDraftListener::class);
		$context->registerEventListener(MailboxesSynchronizedEvent::class, MailboxesSynchronizedSpecialMailboxesUpdater::class);
		$context->registerEventListener(MessageFlaggedEvent::class, MessageCacheUpdaterListener::class);
		$context->registerEventListener(MessageFlaggedEvent::class, SpamReportListener::class);
		$context->registerEventListener(MessageFlaggedEvent::class, HamReportListener::class);
		$context->registerEventListener(MessageFlaggedEvent::class, MoveJunkListener::class);
		$context->registerEventListener(MessageDeletedEvent::class, MessageCacheUpdaterListener::class);
		$context->registerEventListener(MessageSentEvent::class, AddressCollectionListener::class);
		$context->registerEventListener(MessageSentEvent::class, InteractionListener::class);
		$context->registerEventListener(NewMessagesSynchronized::class, MessageKnownSinceListener::class);
		$context->registerEventListener(NewMessagesSynchronized::class, NewMessagesNotifier::class);
		$context->registerEventListener(NewMessagesSynchronized::class, NewMessagesSummarizeListener::class);
		$context->registerEventListener(SynchronizationEvent::class, AccountSynchronizedThreadUpdaterListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
		$context->registerEventListener(NewMessagesSynchronized::class, FollowUpClassifierListener::class);
		$context->registerEventListener(OutOfOfficeStartedEvent::class, OutOfOfficeListener::class);
		$context->registerEventListener(OutOfOfficeEndedEvent::class, OutOfOfficeListener::class);
		$context->registerEventListener(OutOfOfficeChangedEvent::class, OutOfOfficeListener::class);
		$context->registerEventListener(OutOfOfficeClearedEvent::class, OutOfOfficeListener::class);
		$context->registerEventListener(OutOfOfficeScheduledEvent::class, OutOfOfficeListener::class);
		$context->registerEventListener(TaskSuccessfulEvent::class, TaskProcessingListener::class);

		$context->registerMiddleWare(ErrorMiddleware::class);
		$context->registerMiddleWare(ProvisioningMiddleware::class);

		$context->registerDashboardWidget(ImportantMailWidget::class);
		$context->registerDashboardWidget(UnreadMailWidget::class);

		$context->registerSearchProvider(FilteringProvider::class);

		// Added in version 4.0.0
		$context->registerMailProvider(MailProvider::class);

		$context->registerNotifierService(Notifier::class);

		$context->registerSetupCheck(MailTransport::class);
		$context->registerSetupCheck(MailConnectionPerformance::class);

		$context->registerUserMigrator(MailAccountMigrator::class);

		// bypass Horde Translation system
		Horde_Translation::setHandler('Horde_Imap_Client', new HordeTranslationHandler());
		Horde_Translation::setHandler('Horde_Mime', new HordeTranslationHandler());
		Horde_Translation::setHandler('Horde_Smtp', new HordeTranslationHandler());

		// Added in version 5.6.0
		if (class_exists(ContentProviderRegisterEvent::class)) {
			$context->registerEventListener(ContentProviderRegisterEvent::class, ContextChatProvider::class);
			$context->registerEventListener(NewMessagesSynchronized::class, ContextChatProvider::class);
			$context->registerEventListener(MessageDeletedEvent::class, ContextChatProvider::class);
		}
	}

	#[\Override]
	public function boot(IBootContext $context): void {
	}
}
