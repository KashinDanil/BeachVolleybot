<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\HelpMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class GroupHelpCommandProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        $helpMessage = new HelpMessageBuilder(Translator::fromUser($message->from))
            ->build(BOT_USERNAME, $message->chat->isGroupChat());

        $this->telegramSender->sendEphemeralMessage(
            $message->chat->id,
            $message->from->id,
            $message->ephemeralMessageId,
            $helpMessage,
            $message->resolveMessageThreadId(),
        );

        $this->logUserAction($message->from, 'help_command', 'chat=group');
    }
}
