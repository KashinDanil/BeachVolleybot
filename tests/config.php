<?php

declare(strict_types=1);

define('TG_BOT_ACCESS_TOKEN', 'test_token');
define('APP_TOKEN_HASH', 'test_hash');
define('BASE_LOG_DIR', sys_get_temp_dir() . '/bvb_test_logs');
define('BASE_QUEUE_DIR', sys_get_temp_dir() . '/bvb_test_queues');
define('QUEUE_CLASS', \DanilKashin\FileQueue\Queue\FileQueue::class);
define('VERBOSE_LOGGING', false);
define('GAME_ADD_ONS', []);
define('DB_CONNECTION', [
    'type' => 'sqlite',
    'database' => sys_get_temp_dir() . '/bvb_test_db/beach_volleybot.sqlite',
    'error' => PDO::ERRMODE_EXCEPTION,
    'command' => [
        'PRAGMA foreign_keys = ON',
        'PRAGMA journal_mode = WAL',
    ],
]);