<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Telegram\MessageBuilders\GameDetailMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class GameDetailMessageFactory
{
    public static function build(int $gameId): TelegramMessage
    {
        $game = GameFactory::tryFromGameId($gameId, addOns: []);
        $builder = new GameDetailMessageBuilder();

        if (null === $game) {
            return $builder->buildGameNotFound();
        }

        return $builder->buildGameDetail($game);
    }
}
