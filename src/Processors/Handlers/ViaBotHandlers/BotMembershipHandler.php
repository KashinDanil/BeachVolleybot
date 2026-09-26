<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\ViaBotHandlers;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\BotMembershipProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class BotMembershipHandler extends AbstractGroupChatQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMyChatMember()
            && $update->myChatMember->chat->isGroupChat();
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new BotMembershipProcessor($telegramSender);
    }
}
