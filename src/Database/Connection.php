<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use RuntimeException;

final class Connection
{
    private static ?ConcurrentSqliteMedoo $instance = null;

    private function __construct()
    {
    }

    public static function get(): ConcurrentSqliteMedoo
    {
        if (null === self::$instance) {
            self::$instance = self::create();
        }

        return self::$instance;
    }

    public static function set(ConcurrentSqliteMedoo $medoo): void
    {
        self::$instance = $medoo;
    }

    public static function close(): void
    {
        self::$instance = null;
    }

    private static function create(): ConcurrentSqliteMedoo
    {
        $config = DB_CONNECTION;
        $dbDir = dirname($config['database']);

        if (!is_dir($dbDir) && !mkdir($dbDir, 0777, true) && !is_dir($dbDir)) {
            throw new RuntimeException("Cannot create database directory: $dbDir");
        }

        return new ConcurrentSqliteMedoo($config);
    }
}