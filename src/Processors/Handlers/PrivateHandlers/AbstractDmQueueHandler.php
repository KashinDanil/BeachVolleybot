<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Common\QueueName;
use BeachVolleybot\Processors\AbstractQueuedProcessorHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract readonly class AbstractDmQueueHandler extends AbstractQueuedProcessorHandler
{
    public function routeToQueue(TelegramUpdate $update): string
    {
        return QueueName::Dm->forId($update->getFrom()?->id);
    }
}
