<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PinHandlers;

use BeachVolleybot\Processors\AbstractProcessorHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract readonly class AbstractPinQueueHandler extends AbstractProcessorHandler
{
    public function routeToQueue(TelegramUpdate $update): string
    {
        return 'pin_' . $update->message->chat->id;
    }
}
