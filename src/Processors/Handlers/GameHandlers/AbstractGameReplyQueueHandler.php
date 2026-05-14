<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract readonly class AbstractGameReplyQueueHandler extends AbstractGameQueueHandler
{
    protected function resolveGameId(TelegramUpdate $update): ?int
    {
        $message = $update->message ?? $update->editedMessage;
        $inlineQueryId = CallbackData::extractInlineQueryId($message->replyToMessage);

        if (null === $inlineQueryId) {
            return null;
        }

        return new GameManager()->resolveGameIdByInlineQueryId($inlineQueryId);
    }
}
