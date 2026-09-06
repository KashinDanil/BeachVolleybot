<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\GameDateResolver;
use BeachVolleybot\Game\GameMessagePinner;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use DateTimeImmutable;

class PinMessageProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        new GameMessagePinner($this->telegramSender)->pin(
            $message->chat->id,
            $message->messageId,
            $message->toJson(),
            // The card text, not the stored kickoff: the inline flow writes the games row from a
            // separate update, so it may not exist yet when this one is processed.
            GameDateResolver::resolve($message->text ?? '', new DateTimeImmutable("@$message->date")),
        );
    }
}
