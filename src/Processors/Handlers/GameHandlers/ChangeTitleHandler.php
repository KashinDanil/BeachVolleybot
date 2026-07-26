<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\ChangeTitleProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class ChangeTitleHandler extends AbstractGameReplyQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMessage()
            && $this->repliesToGameMessage($update->message)
            && $update->message->hasText()
            && !TimeExtractor::isTimeOnly($update->message->text);
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new ChangeTitleProcessor($telegramSender);
    }
}
