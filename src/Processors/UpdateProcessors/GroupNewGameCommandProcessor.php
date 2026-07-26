<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\NewGameDatePickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use DateTimeImmutable;

class GroupNewGameCommandProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        $picker = new NewGameDatePickerMessageBuilder(Translator::fromUser($message->from), new DateTimeImmutable())->build();

        $sent = $this->telegramSender->sendEphemeralMessage(
            $message->chat->id,
            $message->from->id,
            $message->ephemeralMessageId,
            $picker,
            $message->resolveMessageThreadId(),
        );

        if ($sent) {
            $this->telegramSender->deleteEphemeralMessage(
                $message->chat->id,
                $message->ephemeralMessageId,
                $message->from->id,
            );
        }

        $this->logUserAction($message->from, 'new_game_start');
    }
}
