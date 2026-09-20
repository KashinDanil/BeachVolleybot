<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\NewGame\NewGameDatePickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class GroupNewGameCommandProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        $picker = new NewGameDatePickerMessageBuilder(Translator::fromUser($message->from))->build();

        $this->telegramSender->sendEphemeralMessage(
            $message->chat->id,
            $message->from->id,
            $message->ephemeralMessageId,
            $picker,
            $message->resolveMessageThreadId(),
        );

        $this->logUserAction($message->from, 'new_game_start', 'chat=group');
    }
}
