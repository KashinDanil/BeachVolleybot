<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract readonly class AbstractGameReplyQueueHandler extends AbstractGameQueueHandler
{
    protected function resolveGameId(TelegramUpdate $update): ?int
    {
        $message = $update->message ?? $update->editedMessage;
        $inlineQueryId = GameCallbackData::extractInlineQueryId($message->replyToMessage);

        if (null === $inlineQueryId) {
            Logger::logVerbose('Meta-button missing inline_query_id' . PHP_EOL);

            return null;
        }

        $gameId = new GameManager()->resolveGameIdByInlineQueryId($inlineQueryId);

        if (null === $gameId) {
            Logger::logVerbose('Game not found by inline_query_id: ' . $inlineQueryId . PHP_EOL);

            return null;
        }

        return $gameId;
    }
}
