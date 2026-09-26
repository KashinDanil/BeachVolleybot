<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\ViaBotHandlers;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\ChatMigrationProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class ChatMigrationHandler extends AbstractGroupChatQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMessage()
            && null !== $update->message->migrateToChatId;
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new ChatMigrationProcessor($telegramSender);
    }
}
