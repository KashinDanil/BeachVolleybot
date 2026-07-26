<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLiveLocationProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class SetLiveLocationHandler extends AbstractGameReplyQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasEditedMessage()
            && $this->repliesToGameMessage($update->editedMessage)
            && $update->editedMessage->hasLocation();
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new SetLiveLocationProcessor($telegramSender);
    }
}
