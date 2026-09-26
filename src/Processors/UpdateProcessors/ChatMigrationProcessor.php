<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Database\AuthorizedChatRepository;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

final class ChatMigrationProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        new AuthorizedChatRepository(Connection::get())
            ->reauthorizeMigratedChat($message->chat->id, $message->migrateToChatId);
    }
}
