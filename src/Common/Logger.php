<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

use BeachVolleybot\Errors\ErrorInterface;

class Logger
{
    private const string VERBOSE_LOG_FILE      = 'verbose.log';
    private const string APP_LOG_FILE          = 'app.log';
    private const string WEB_LOG_FILE          = 'web.log';
    private const string USER_ACTIONS_LOG_FILE = 'user_actions.log';

    private static function log(string $message, string $logFile): void
    {
        $filepath = BASE_LOG_DIR . '/' . $logFile;
        $content = sprintf('[%s] %s%s', date('c'), $message, PHP_EOL);
        file_put_contents($filepath, $content, FILE_APPEND | LOCK_EX);
    }

    public static function logVerbose(string $message): void
    {
        VERBOSE_LOGGING && self::log($message, self::VERBOSE_LOG_FILE);
    }

    public static function logApp(string $message): void
    {
        self::log($message, self::APP_LOG_FILE);
    }

    public static function logWeb(string $message): void
    {
        self::log($message, self::WEB_LOG_FILE);
    }

    public static function logUserAction(int $userId, string $name, ?string $username, string $action, string $details = ''): void
    {
        $message = sprintf(
            "User id=%d, name='%s', username='%s', action='%s', details='%s'",
            $userId,
            $name,
            $username ?? '',
            $action,
            $details ?? '',
        );
        self::log($message, self::USER_ACTIONS_LOG_FILE);
    }

    public static function logUnauthorizedAccessAttempt(ErrorInterface $error): void
    {
        $message = sprintf(
            'Unauthorized access attempt: Validation error=\'%s\', data=\'%s\', IP=\'%s\', URL=\'%s\', Method=\'%s\', UserAgent=\'%s\'',
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
