<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Telegram\MessageBuilders\GameDetailMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\PlayersListMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class PlayersListMessageFactory
{
    public static function build(int $gameId, int $page): TelegramMessage
    {
        $game = GameFactory::tryFromGameId($gameId, addOns: []);

        if (null === $game) {
            return new GameDetailMessageBuilder()->buildGameNotFound();
        }

        return new PlayersListMessageBuilder()->build($game, $page);
    }
}
