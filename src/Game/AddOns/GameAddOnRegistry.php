<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

final class GameAddOnRegistry
{
    /**
     * @param class-string<GameAddOnInterface> $addOn
     * @param list<class-string<GameAddOnInterface>> $addOns
     */
    public static function isEnabled(string $addOn, array $addOns = GAME_ADD_ONS): bool
    {
        return in_array($addOn, $addOns, true);
    }
}