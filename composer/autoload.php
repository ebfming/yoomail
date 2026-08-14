<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// 显式加载应用级函数文件。
// Nextcloud 的 registerAutoloading() 在 require composer/autoload.php 时,
// vendor 的 files 自动加载机制不保证执行应用自身的 lib/functions.php,
// 导致 OCA\YooMail\array_flat_map() 等函数在 HTTP 请求下未定义。
require_once __DIR__ . '/../lib/functions.php';
