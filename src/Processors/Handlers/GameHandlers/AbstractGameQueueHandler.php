<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Processors\AbstractProcessorHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract readonly class AbstractGameQueueHandler extends AbstractProcessorHandler
{
    public function routeToQueue(TelegramUpdate $update): ?string
    {
        $gameId = $this->resolveGameId($update);

        if (null === $gameId) {
            return null;
        }

        return 'game_' . $gameId;
    }

    abstract protected function resolveGameId(TelegramUpdate $update): ?int;
}
