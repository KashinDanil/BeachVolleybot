<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;

abstract class AbstractActionReplyProcessor extends AbstractActionProcessor
{
    protected function reactWithCheckmarkAndDelete(TelegramMessage $message): void
    {
        $this->reactWithCheckmark($message);
        $this->bot->deleteMessage($message->chat->id, $message->messageId);
    }

    protected function reactWithCheckmark(TelegramMessage $message): void
    {
        $this->react($message, '✅');
    }

    protected function reactConfused(TelegramMessage $message): void
    {
        $this->react($message, '😕');
    }

    private function react(TelegramMessage $message, string $emoji): void
    {
        $this->bot->call('setMessageReaction', [
            'chat_id' => $message->chat->id,
            'message_id' => $message->messageId,
            'reaction' => json_encode([['type' => 'emoji', 'emoji' => $emoji]]),
        ]);
        $this->bot->deleteMessage($message->chat->id, $message->messageId);
    }
}
