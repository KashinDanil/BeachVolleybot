<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PinHandlers;

use BeachVolleybot\Common\QueueName;
use BeachVolleybot\Processors\AbstractQueuedProcessorHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract readonly class AbstractPinQueueHandler extends AbstractQueuedProcessorHandler
{
    public function routeToQueue(TelegramUpdate $update): string
    {
        return QueueName::Pin->forId($update->message->chat->id);
    }
}
