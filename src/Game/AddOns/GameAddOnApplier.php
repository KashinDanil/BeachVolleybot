<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;

final class GameAddOnApplier
{
    /** @param list<class-string<GameAddOnInterface>> $addOns */
    public static function apply(Game $game, array $addOns = GAME_ADD_ONS): GameInterface
    {
        $game->init();

        foreach ($addOns as $addOnClass) {
            new $addOnClass()->applyTo($game);
        }

        return $game;
    }
}
