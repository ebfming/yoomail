<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load app-level helper functions explicitly.
// When Nextcloud requires composer/autoload.php during registerAutoloading(),
// Composer's vendor "files" autoloading does not reliably execute the app's
// own lib/functions.php, which can leave helpers such as
// OCA\YooMail\array_flat_map() undefined in HTTP requests.
require_once __DIR__ . '/../lib/functions.php';
