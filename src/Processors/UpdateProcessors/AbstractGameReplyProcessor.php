<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract class AbstractGameReplyProcessor extends AbstractActionReplyProcessor
{
    final public function process(TelegramUpdate $update): void
    {
        $message = $this->extractMessage($update);

        if (null === $message || !$message->hasReplyToMessage()) {
            return;
        }

        $inlineQueryId = CallbackData::extractInlineQueryId($message->replyToMessage);

        if (null === $inlineQueryId) {
            return;
        }

        $gameRecord = new GameManager()->findGameRecord($inlineQueryId);

        if (null === $gameRecord) {
            return;
        }

        if (GameDateTimeResolver::isKickoffDayPast($gameRecord->title, $gameRecord->createdAt)) {
            return;
        }

        $this->handle($update, $gameRecord);
    }

    abstract protected function handle(TelegramUpdate $update, GameRecord $gameRecord): void;

    protected function extractMessage(TelegramUpdate $update): ?TelegramMessage
    {
        return $update->message;
    }
}