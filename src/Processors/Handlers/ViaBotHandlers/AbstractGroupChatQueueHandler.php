<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\ViaBotHandlers;

use BeachVolleybot\Processors\AbstractQueuedProcessorHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract readonly class AbstractGroupChatQueueHandler extends AbstractQueuedProcessorHandler
{
    public function routeToQueue(TelegramUpdate $update): string
    {
        $chat = ($update->message ?? $update->editedMessage)?->chat ?? $update->myChatMember->chat;

        return 'group_chat_' . $chat->id;
    }
}
