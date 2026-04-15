<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Log\LogFileRepository;
use BeachVolleybot\Telegram\MessageBuilders\LogTailMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class LogTailMessageFactory
{
    public static function build(string $filename): TelegramMessage
    {
        $logFileRepository = new LogFileRepository();
        $entry = $logFileRepository->find($filename);
        $tailContent = $logFileRepository->readTail($filename);

        return new LogTailMessageBuilder()->buildLogTail($entry, $tailContent);
    }
}
