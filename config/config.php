<?php

declare(strict_types=1);

$paths = parse_ini_file(__DIR__ . '/paths.env');

define('VERBOSE_LOGGING', false);

define('ADMINS_TELEGRAM_USER_IDS', []);
define('BOT_USERNAME', 'XXX');
define('TG_BOT_ACCESS_TOKEN', 'XXX');
define('APP_TOKEN_HASH', 'XXX');

define('BASE_LOG_DIR', __DIR__ . '/' . $paths['LOGS_DIR']);
define('BASE_QUEUE_DIR', __DIR__ . '/' . $paths['QUEUES_DIR']);
define('QUEUE_CLASS', \DanilKashin\FileQueue\Queue\FileQueue::class);
define('GAME_ADD_ONS', [
    \BeachVolleybot\Game\AddOns\MergeConsecutiveSlotsAddOn::class,
    \BeachVolleybot\Game\AddOns\StylizeTitleAddOn::class,
]);
define('TG_MAX_REQUESTS_PER_SECOND', 19);
define('DB_CONNECTION', [
    'type' => 'sqlite',
    'database' => __DIR__ . '/' . $paths['DB_DATA_DIR'] . '/' . $paths['DB_FILENAME'],
    'error' => PDO::ERRMODE_EXCEPTION,
    'command' => [
        'PRAGMA foreign_keys = ON',
        'PRAGMA journal_mode = WAL',
    ],
]);
