<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

class Logger
{
    private const BASE_LOG_DIR = __DIR__ . '/../../logs';

    private const APP_LOG_FILE = 'app.log';
    private const WEB_LOG_FILE = 'web.log';

    public static function log(string $message, string $logFile = self::APP_LOG_FILE): void
    {
        $filepath = self::BASE_LOG_DIR . '/' . $logFile;
        $datetime = date('Y-m-d H:i:s');
        $content = sprintf('[%s] %s', $datetime, $message) . PHP_EOL;
        file_put_contents($filepath, $content, FILE_APPEND | LOCK_EX);
    }

    public static function logWeb($message): void
    {
        self::log($message, self::WEB_LOG_FILE);
    }

    public static function logUnauthorizedAccessAttempt(): void
    {
        $message = sprintf(
            "Unauthorized access attempt: IP=%s, URL=%s, Method=%s, UserAgent=%s",
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['REQUEST_URI'] ?? null,
            $_SERVER['REQUEST_METHOD'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        );
        self::logWeb($message);
    }
}
