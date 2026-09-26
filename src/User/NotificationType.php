<?php

declare(strict_types=1);

namespace BeachVolleybot\User;

use BeachVolleybot\Common\BitFlag;

enum NotificationType: int implements BitFlag
{
    case GameReachedMinimumPlayers = 1;
    case GameShortBeforeKickoff = 2;
    case PromotedIntoGame = 3;
    case BumpedFromGame = 4;

    public function bit(): int
    {
        return 1 << $this->value;
    }
}
