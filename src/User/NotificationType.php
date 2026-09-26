<?php

declare(strict_types=1);

namespace BeachVolleybot\User;

use BeachVolleybot\Common\BitFlag;

enum NotificationType: int implements BitFlag
{
    case GameReachedMinimumPlayers = 1;
    case PromotedIntoGame          = 2;
    case BumpedFromGame            = 3;
    case GameShortBeforeKickoff    = 4;

    public function bit(): int
    {
        return 1 << $this->value;
    }
}
