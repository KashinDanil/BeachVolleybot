<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Game\Models\GameInterface;

interface GameAddOnInterface
{
    public function transform(GameInterface $game): GameInterface;
}
