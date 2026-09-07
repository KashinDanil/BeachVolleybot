<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location;

use BeachVolleybot\Weather\Location\Models\LocationCoordinates;

final readonly class GameLocationResolver
{
    public function resolve(?string $location, ?string $venueName): LocationCoordinates
    {
        return LocationCoordinates::tryParse($location) ?? KnownVenues::findByNameOrDefault($venueName)->coordinates;
    }
}
