<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\MessageBuilders\Factories\UserGamesListMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class UserGamesListCommandProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        $listMessage = UserGamesListMessageFactory::build(
            createdBy: $message->from->id,
            page: 1,
            translator: Translator::fromUser($message->from),
        );

        $this->telegramSender->sendMessage($message->chat->id, $listMessage);
        $this->telegramSender->deleteMessage($message->chat->id, $message->messageId);
        $this->logUserAction($message->from, 'games_list_opened');
    }
}
