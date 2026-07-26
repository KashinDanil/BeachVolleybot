<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

use BeachVolleybot\Game\GameFactory;

readonly class GameMessageRefresher
{
    public function __construct(
        private TelegramMessageSender $sender,
    ) {
    }

    public function refresh(int $gameId): void
    {
        $game = GameFactory::fromGameId($gameId);
        $message = $game->buildTelegramMessage();

        foreach ($game->getMessageTargets() as $target) {
            $this->sender->editGameMessage($target, $message);
        }
    }
}
