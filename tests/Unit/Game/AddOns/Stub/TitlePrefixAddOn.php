<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\AddOns\Stub;

use BeachVolleybot\Game\AddOns\GameAddOnInterface;
use BeachVolleybot\Game\Models\Game;

final class TitlePrefixAddOn implements GameAddOnInterface
{
    public function __construct(private readonly string $prefix = '[Modified]')
    {
    }

    public function applyTo(Game $game): void
    {
        $game->title = $this->prefix . ' ' . $game->title;
    }
}
