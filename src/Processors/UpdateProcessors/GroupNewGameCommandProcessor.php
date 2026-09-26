<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\NewGame\NewGameDatePickerMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\UnauthorizedGroupMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage as OutgoingTelegramMessage;
use BeachVolleybot\Validator\Rules\Game\AuthorizedChatRule;

class GroupNewGameCommandProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        if (!AuthorizedChatRule::isSatisfiedBy($message->chat)) {
            $this->sendEphemeral($message, new UnauthorizedGroupMessageBuilder(Translator::fromUser($message->from))->build());

            return;
        }

        $this->sendEphemeral($message, new NewGameDatePickerMessageBuilder(Translator::fromUser($message->from))->build());

        $this->logUserAction($message->from, 'new_game_start', 'chat=group');
    }

    private function sendEphemeral(TelegramMessage $message, OutgoingTelegramMessage $ephemeral): void
    {
        $this->telegramSender->sendEphemeralMessage(
            $message->chat->id,
            $message->from->id,
            $message->ephemeralMessageId,
            $ephemeral,
            $message->resolveMessageThreadId(),
        );
    }
}
