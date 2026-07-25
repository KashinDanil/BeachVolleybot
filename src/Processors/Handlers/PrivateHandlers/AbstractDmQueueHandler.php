<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\AbstractQueuedProcessorHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract readonly class AbstractDmQueueHandler extends AbstractQueuedProcessorHandler
{
    public function routeToQueue(TelegramUpdate $update): string
    {
        $userId = $update->callbackQuery?->from->id
            ?? $update->message?->from->id
            ?? $update->editedMessage?->from->id;

        return 'dm_' . $userId;
    }
}
