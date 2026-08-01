<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\NewGameHandlers;

use BeachVolleybot\Common\Command;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\AbstractDmQueueHandler;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UserProcessors\UserNewGameCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class UserNewGameCommandHandler extends AbstractDmQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMessage()
            && $update->message->chat->isPrivate()
            && Command::NewGame->matches($update->message->text);
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new UserNewGameCommandProcessor($telegramSender);
    }
}
