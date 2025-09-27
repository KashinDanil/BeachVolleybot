<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

use BeachVolleybot\Errors\ErrorInterface;
use RuntimeException;

class Logger
{
    private const string BASE_LOG_DIR = __DIR__ . '/../../logs';

    private const string APP_LOG_FILE = 'app.log';
    private const string WEB_LOG_FILE = 'web.log';

    public static function log(string $message, string $logFile = self::APP_LOG_FILE): void
    {
        $filepath = self::BASE_LOG_DIR . '/' . $logFile;

        self::ensureDirectory($filepath);

        $content = sprintf('[%s] %s%s', date('c'), $message, PHP_EOL);
        file_put_contents($filepath, $content, FILE_APPEND | LOCK_EX);
    }

    private static function ensureDirectory(string $filePath): void
    {
        $dir = dirname($filePath);
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Failed to create directory "%s"', $dir));
        }
    }

    public static function logWeb(string $message): void
    {
        self::log($message, self::WEB_LOG_FILE);
    }

    public static function logUnauthorizedAccessAttempt(ErrorInterface $error): void
    {
        $message = sprintf(
            'Unauthorized access attempt: Validation error="%s", data="%s", IP="%s", URL="%s", Method="%s", UserAgent="%s"',
            $error->getMessage(),
            json_encode($error->getData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['REQUEST_URI'] ?? null,
            $_SERVER['REQUEST_METHOD'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        );
        self::logWeb($message);
    }
}
