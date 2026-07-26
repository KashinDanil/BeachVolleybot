<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\AbstractProcessorHandler;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\GroupHelpCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class GroupHelpCommandHandler extends AbstractProcessorHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMessage()
            && $update->message->chat->isGroupChat()
            && $update->message->isEphemeral()
            && $this->isHelpCommand($update->message->text);
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new GroupHelpCommandProcessor($telegramSender);
    }

    private function isHelpCommand(?string $text): bool
    {
        $command = UserHelpCommandHandler::COMMAND;

        return $command === $text || $command . '@' . BOT_USERNAME === $text;
    }
}
