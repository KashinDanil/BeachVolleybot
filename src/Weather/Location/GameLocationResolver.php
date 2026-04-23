<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Weather\Location\Models\DefaultLocationCoordinates;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;

final readonly class GameLocationResolver
{
    public function resolve(GameInterface $game): LocationCoordinates
    {
        return LocationCoordinates::tryParse($game->getLocation())
            ?? KnownVenues::findInTitle($game->getTitle())
            ?? new DefaultLocationCoordinates();
    }
}
