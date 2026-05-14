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
        return $update->hasCallbackQuery()
            && $update->callbackQuery->isInline()
            && null !== GameCallbackData::fromJson($update->callbackQuery->data);
    }

    protected function resolveGameId(TelegramUpdate $update): ?int
    {
        $inlineMessageId = $update->callbackQuery->inlineMessageId;
        $gameId = new GameManager()->resolveGameIdByInlineMessageId($inlineMessageId);

        if (null === $gameId) {
            Logger::logVerbose('Game not found by inline_message_id: ' . $inlineMessageId . PHP_EOL);

            return null;
        }

        return $gameId;
    }

    protected function getCallbackDataClass(): string
    {
        return GameCallbackData::class;
    }
}
