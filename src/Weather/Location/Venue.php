<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location;

use BeachVolleybot\Weather\Location\Models\LocationCoordinates;

final readonly class Venue
{
    /** @param non-empty-list<string> $aliases */
    public function __construct(
        public LocationCoordinates $coordinates,
        public array $aliases,
    ) {
    }
}