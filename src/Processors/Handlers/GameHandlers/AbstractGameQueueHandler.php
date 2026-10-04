<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Common\QueueName;
use BeachVolleybot\Processors\AbstractSenderQueueHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract readonly class AbstractGameQueueHandler extends AbstractSenderQueueHandler
{
    public function routeToQueue(TelegramUpdate $update): ?string
    {
        $gameId = $this->resolveGameId($update);

        if (null === $gameId) {
            return null;
        }

        return QueueName::Game->forId($gameId);
    }

    abstract protected function resolveGameId(TelegramUpdate $update): ?int;
}
