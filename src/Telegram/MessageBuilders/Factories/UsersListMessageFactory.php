<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Telegram\MessageBuilders\Admin\UsersListMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\Game\GameDetailMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class UsersListMessageFactory
{
    public static function build(int $gameId, int $page): TelegramMessage
    {
        // addOns: [] skips merging/stylizing/weather, not promotion — that runs upstream in GameBuilder.
        $game = GameFactory::tryFromGameId($gameId, addOns: []);

        if (null === $game) {
            return new GameDetailMessageBuilder()->buildGameNotFound();
        }

        return new UsersListMessageBuilder()->build($game, $page);
    }
}
