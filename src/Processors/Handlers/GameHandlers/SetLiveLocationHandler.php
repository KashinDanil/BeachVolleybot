<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLiveLocationProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserRecord;

final readonly class SetLiveLocationHandler extends AbstractGameReplyQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasEditedMessage()
            && $this->repliesToGameMessage($update->editedMessage)
            && $update->editedMessage->hasLocation();
    }

    protected function createSenderProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
        UserRecord $sender,
    ): AbstractActionProcessor {
        return new SetLiveLocationProcessor($telegramSender);
    }
}
