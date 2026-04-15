<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Log\LogFileRepository;
use BeachVolleybot\Telegram\MessageBuilders\KeyboardPagination;
use BeachVolleybot\Telegram\MessageBuilders\LogsListMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class LogsListMessageFactory
{
    private const int LOGS_PER_PAGE = 8;

    public static function build(int $page): TelegramMessage
    {
        $logFileRepository = new LogFileRepository();
        $totalLogs = $logFileRepository->countAll();
        $pagination = new KeyboardPagination($totalLogs, self::LOGS_PER_PAGE, $page);
        $logEntries = $logFileRepository->findPaginated(self::LOGS_PER_PAGE, $pagination->offset);

        return new LogsListMessageBuilder()->buildLogsList($logEntries, $pagination);
    }
}
