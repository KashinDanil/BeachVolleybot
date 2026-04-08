<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Game\Models\Game;

interface GameAddOnInterface
{
    public function applyTo(Game $game): void;
}
