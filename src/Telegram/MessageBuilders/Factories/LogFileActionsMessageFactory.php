<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Log\LogFileRepository;
use BeachVolleybot\Telegram\MessageBuilders\LogFileActionsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class LogFileActionsMessageFactory
{
    public static function build(string $filename): TelegramMessage
    {
        $entry = new LogFileRepository()->find($filename);

        return new LogFileActionsMessageBuilder()->buildLogFileActions($entry);
    }
}
