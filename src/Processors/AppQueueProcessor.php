<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Telegram\Incoming\TelegramUpdate;
use DanilKashin\FileQueue\Queue\QueueMessage;

class AppQueueProcessor implements QueueProcessorInterface
{
    public function process(QueueMessage $message): bool
    {
        $update = TelegramUpdate::fromArray($message->payload);

        return true;
    }
}