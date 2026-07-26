<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\MessageBuilders\NewGameDatePickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use DateTimeImmutable;

class UserNewGameCommandProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        $picker = new NewGameDatePickerMessageBuilder(Translator::fromUser($message->from), new DateTimeImmutable())->build();

        $sentMessageId = $this->telegramSender->sendMessage($message->chat->id, $picker);

        if (0 !== $sentMessageId) {
            $this->telegramSender->deleteMessage($message->chat->id, $message->messageId);
        }

        $this->logUserAction($message->from, 'new_game_start');
    }
}
