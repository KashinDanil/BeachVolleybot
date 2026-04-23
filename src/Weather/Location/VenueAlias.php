<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location;

use BeachVolleybot\Weather\Location\Models\LocationCoordinates;

final readonly class VenueAlias
{
    public function __construct(
        public string $alias,
        public LocationCoordinates $coordinates,
    ) {
    }
}