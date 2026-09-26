<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\ViaBotHandlers;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\EditedViaBotMessageProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class EditedViaBotMessageHandler extends AbstractGroupChatQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasEditedMessage()
            && $update->editedMessage->chat->isGroupChat()
            && $update->editedMessage->isViaThisBot()
            && $update->editedMessage->hasInlineKeyboard();
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new EditedViaBotMessageProcessor($telegramSender);
    }
}
