<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\NewGameHandlers;

use BeachVolleybot\Processors\AbstractProcessorHandler;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\GroupNewGameCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class GroupNewGameCommandHandler extends AbstractProcessorHandler
{
    public const string COMMAND = '/new_game';

    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMessage()
            && $update->message->chat->isGroupChat()
            && $update->message->isEphemeral()
            && $this->isNewGameCommand($update->message->text);
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new GroupNewGameCommandProcessor($telegramSender);
    }

    private function isNewGameCommand(?string $text): bool
    {
        return self::COMMAND === $text || self::COMMAND . '@' . BOT_USERNAME === $text;
    }
}
