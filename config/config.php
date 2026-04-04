<?php

declare(strict_types=1);

define('TG_BOT_ACCESS_TOKEN', 'XXX');
define('APP_TOKEN_HASH', 'XXX');
define('BASE_LOG_DIR', 'XXX');
define('BASE_QUEUE_DIR', 'XXX');
define('QUEUE_CLASS', \DanilKashin\FileQueue\Queue\FileQueue::class);
define('VERBOSE_LOGGING', false);
define('GAME_ADD_ONS', []);
define('DB_CONNECTION', [
    'type' => 'sqlite',
    'database' => __DIR__ . '/../db/data/beach_volleybot.sqlite',
    'error' => PDO::ERRMODE_EXCEPTION,
    'command' => [
        'PRAGMA foreign_keys = ON',
        'PRAGMA journal_mode = WAL',
    ],
]);
