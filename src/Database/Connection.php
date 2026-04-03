<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use Medoo\Medoo;
use RuntimeException;

final class Connection
{
    private static ?Medoo $instance = null;

    private function __construct()
    {
    }

    public static function get(): Medoo
    {
        if (null === self::$instance) {
            self::$instance = self::create();
        }

        return self::$instance;
    }

    public static function set(Medoo $medoo): void
    {
        self::$instance = $medoo;
    }

    public static function close(): void
    {
        self::$instance = null;
    }

    private static function create(): Medoo
    {
        $config = DB_CONNECTION;
        $dbDir = dirname($config['database']);

        if (!is_dir($dbDir) && !mkdir($dbDir, 0777, true) && !is_dir($dbDir)) {
            throw new RuntimeException("Cannot create database directory: $dbDir");
        }

        return new Medoo($config);
    }
}