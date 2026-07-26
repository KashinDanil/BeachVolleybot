<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract readonly class AbstractGameReplyQueueHandler extends AbstractGameQueueHandler
{
    protected function repliesToGameMessage(?TelegramMessage $message): bool
    {
        return null !== $message
            && $message->hasReplyToMessage()
            && null !== GameCallbackData::extractGameKey($message->replyToMessage);
    }

    protected function resolveGameId(TelegramUpdate $update): ?int
    {
        $message = $update->message ?? $update->editedMessage;
        $gameKey = GameCallbackData::extractGameKey($message->replyToMessage);

        if (null === $gameKey) {
            Logger::logVerbose('Meta-button missing game key' . PHP_EOL);

            return null;
        }

        $gameId = new GameManager()->resolveGameIdByGameKey($gameKey);

        if (null === $gameId) {
            Logger::logVerbose('Game not found by game key: ' . $gameKey . PHP_EOL);

            return null;
        }

        return $gameId;
    }
}
