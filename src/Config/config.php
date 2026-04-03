<?php

declare(strict_types=1);

const TG_BOT_ACCESS_TOKEN = 'XXX';
const APP_TOKEN_HASH = 'XXX';
const BASE_LOG_DIR = 'XXX';
const BASE_QUEUE_DIR = 'XXX';
const QUEUE_CLASS = \DanilKashin\FileQueue\Queue\FileQueue::class;
const VERBOSE_LOGGING = false;
const DB_CONNECTION = [
    'type' => 'sqlite',
    'database' => __DIR__ . '/../../db/beach_volleybot.sqlite',
    'error' => PDO::ERRMODE_EXCEPTION,
    'command' => [
        'PRAGMA foreign_keys = ON',
        'PRAGMA journal_mode = WAL',
    ],
];
