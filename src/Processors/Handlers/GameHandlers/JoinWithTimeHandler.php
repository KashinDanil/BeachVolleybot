<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\JoinWithTimeProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserRecord;

final readonly class JoinWithTimeHandler extends AbstractGameReplyQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMessage()
            && $this->repliesToGameMessage($update->message)
            && $update->message->hasText()
            && null !== TimeExtractor::extract($update->message->text);
    }

    protected function createSenderProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
        UserRecord $sender,
    ): AbstractActionProcessor {
        return new JoinWithTimeProcessor($telegramSender);
    }
}
