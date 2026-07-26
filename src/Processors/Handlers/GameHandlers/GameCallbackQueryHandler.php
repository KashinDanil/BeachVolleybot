<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Processors\Handlers\Traits\CallbackProcessorResolverTrait;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

final readonly class GameCallbackQueryHandler extends AbstractGameQueueHandler
{
    use CallbackProcessorResolverTrait;

    public function matches(TelegramUpdate $update): bool
    {
        if (!$update->hasCallbackQuery() || null === GameCallbackData::fromJson($update->callbackQuery->data)) {
            return false;
        }

        $callbackQuery = $update->callbackQuery;

        return $callbackQuery->isInline() || $callbackQuery->hasMessage();
    }

    protected function resolveGameId(TelegramUpdate $update): ?int
    {
        $gameId = new GameManager()->resolveGameIdByTarget($update->callbackQuery->toGameMessageTarget());

        if (null === $gameId) {
            Logger::logVerbose('Game not found for callback target' . PHP_EOL);

            return null;
        }

        return $gameId;
    }

    protected function getCallbackDataClass(): string
    {
        return GameCallbackData::class;
    }
}
