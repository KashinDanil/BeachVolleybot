<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Telegram\TelegramMessageSender;

readonly class GameMessagePoster
{
    public function __construct(
        private TelegramMessageSender $telegramSender,
        private GameManager $gameManager,
    ) {
    }

    public function post(NewGameData $newGameData, int $chatId, ?int $messageThreadId): ?PostedGame
    {
        $sentMessageId = $this->telegramSender->sendMessage(
            $chatId,
            NewGameFactory::create($newGameData)->buildTelegramMessage(),
            $messageThreadId,
        );

        if (0 === $sentMessageId) {
            Logger::logApp('Failed to post game message to chat ' . $chatId);

            return null;
        }

        $gameId = $this->gameManager->createGame($newGameData);
        $this->gameManager->addChatMessage($gameId, $chatId, $sentMessageId);

        return new PostedGame($gameId, $sentMessageId);
    }
}
