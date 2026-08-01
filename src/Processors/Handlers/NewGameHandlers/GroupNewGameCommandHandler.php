<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\NewGameHandlers;

use BeachVolleybot\Common\Command;
use BeachVolleybot\Processors\AbstractProcessorHandler;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\GroupNewGameCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class GroupNewGameCommandHandler extends AbstractProcessorHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMessage()
            && $update->message->chat->isGroupChat()
            && $update->message->isEphemeral()
            && Command::NewGame->matches($update->message->text);
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new GroupNewGameCommandProcessor($telegramSender);
    }
}
