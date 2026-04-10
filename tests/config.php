<?php

declare(strict_types=1);

$testTempDirectory = '/tmp/bvb_test_' . get_current_user();
$logDirectory = $testTempDirectory . '/logs';
$queueDirectory = $testTempDirectory . '/queues';
$databaseDirectory = $testTempDirectory . '/db';

foreach ([$logDirectory, $queueDirectory, $databaseDirectory] as $directory) {
    if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Failed to create directory: ' . $directory);
    }
}

define('BOT_USERNAME', 'test_bot');
define('TG_BOT_ACCESS_TOKEN', 'test_token');
define('APP_TOKEN_HASH', 'test_hash');
define('BASE_LOG_DIR', $logDirectory);
define('BASE_QUEUE_DIR', $queueDirectory);
define('QUEUE_CLASS', \DanilKashin\FileQueue\Queue\FileQueue::class);
define('VERBOSE_LOGGING', false);
define('GAME_ADD_ONS', [
    \BeachVolleybot\Game\AddOns\MergeConsecutiveSlotsAddOn::class,
]);
define('DB_CONNECTION', [
    'type' => 'sqlite',
    'database' => $databaseDirectory . '/beach_volleybot.sqlite',
    'error' => PDO::ERRMODE_EXCEPTION,
    'command' => [
        'PRAGMA foreign_keys = ON',
        'PRAGMA journal_mode = WAL',
    ],
]);