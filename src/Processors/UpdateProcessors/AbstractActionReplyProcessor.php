<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use TelegramBot\Api\HttpException;

abstract class AbstractActionReplyProcessor extends AbstractActionProcessor
{
    protected function reactWithCheckmarkAndDelete(TelegramMessage $message): void
    {
        try {
            $this->reactWithCheckmark($message);
            $this->telegramSender->deleteMessage($message->chat->id, $message->messageId);
        } catch (HttpException) {
        }
    }

    protected function reactWithCheckmark(TelegramMessage $message): void
    {
        $this->react($message, '👍');
    }

    protected function reactConfused(TelegramMessage $message): void
    {
        $this->react($message, '👎');
    }

    private function react(TelegramMessage $message, string $emoji): void
    {
        try {
            $this->telegramSender->setMessageReaction($message->chat->id, $message->messageId, $emoji);
        } catch (HttpException) {
        }
    }
}
