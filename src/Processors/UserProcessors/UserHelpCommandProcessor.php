<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\MessageBuilders\HelpMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class UserHelpCommandProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        $welcomeMessage = new HelpMessageBuilder(Translator::fromUser($message->from))->build(BOT_USERNAME);

        $this->telegramSender->sendMessage($message->chat->id, $welcomeMessage);
        $this->telegramSender->deleteMessage($message->chat->id, $message->messageId);
        $this->logUserAction($message->from, 'help_command');
    }
}
