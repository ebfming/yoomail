<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * @version b-2026.08.21
 */

/**
 * Nextcloud Mail realtime service entry point.
 *
 * Usage:
 *   php realtime/server.php start        # start the service
 *   php realtime/server.php stop         # stop the service
 *   php realtime/server.php restart      # restart the service
 *   php realtime/server.php status       # show status
 *   php realtime/server.php -d           # run in debug mode (foreground)
 *
 * Requires:
 *   - Nextcloud base at <nextcloud>/lib/base.php
 *   - Workerman installed in realtime/vendor
 */

define('OC_CONSOLE', true);

// realtime/server.php is at <nc>/apps-extra/yoomail/realtime/server.php
// dirname(__DIR__, 3) = <nc root>
$ncRoot = dirname(__DIR__, 3);

require_once $ncRoot . '/lib/base.php';
require_once __DIR__ . '/vendor/autoload.php';

use OCA\YooMail\IMAP\IMAPClientFactory;
use OCA\YooMail\IMAP\MailboxSync;
use OCA\YooMail\Service\AccountService;
use OCA\YooMail\Service\Sync\ImapToDbSynchronizer;
use OCA\YooMailRealtime\ImapIdleManager;
use OCA\YooMailRealtime\RealtimeServer;
use OCA\YooMailRealtime\RealtimeSyncService;
use OCA\YooMailRealtime\RealtimeTokenService;
use OCA\YooMailRealtime\UserConnectionRegistry;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

try {
    // Ensure YooMail itself is loaded so its services/classes are resolvable
    // even when the upstream `mail` app is not enabled side-by-side.
    \OC_App::loadApp('yoomail');

    $server = \OC::$server;

    // Resolve existing Mail services from the Nextcloud container
    $accountService = $server->get(AccountService::class);
    $crypto = $server->get(ICrypto::class);
    $config = $server->get(IConfig::class);
    $logger = $server->get(LoggerInterface::class);
    $imapClientFactory = $server->get(IMAPClientFactory::class);
    $mailboxSync = $server->get(MailboxSync::class);
    $syncService = $server->get(ImapToDbSynchronizer::class);
    $eventDispatcher = $server->get(IEventDispatcher::class);

    // Assemble our realtime components
    $registry = new UserConnectionRegistry();
    $tokenService = new RealtimeTokenService($config, 60);

    $realtimeSyncService = new RealtimeSyncService(
        $imapClientFactory,
        $mailboxSync,
        $syncService,
        $eventDispatcher,
        $logger,
    );

    $idleManager = new ImapIdleManager(
        $accountService,
        $crypto,
        $realtimeSyncService,
        $registry,
        $logger,
        (int)$config->getAppValue('yoomail', 'realtime_idle_max_accounts', '200'),
        (int)$config->getAppValue('yoomail', 'realtime_idle_refresh_seconds', '1500'),
    );

    $realtimeServer = new RealtimeServer(
        $config,
        $registry,
        $tokenService,
        $realtimeSyncService,
        $idleManager,
        $logger,
    );

    $realtimeServer->run();
} catch (\Throwable $e) {
    fwrite(STDERR, '[yoomail-realtime] FATAL: ' . get_class($e) . ': ' . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
