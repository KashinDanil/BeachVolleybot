<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameMessagePinner;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class PinMessageProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        new GameMessagePinner($this->telegramSender)->pin(
            $message->chat->id,
            $message->messageId,
            $message->toJson(),
            $message->text ?? '',
            $message->date,
        );
    }
}
