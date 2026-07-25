<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location;

/**
 * One normalized spelling a venue can be recognised by. Index entry of VenueDirectory.
 */
final readonly class VenueAlias
{
    public function __construct(
        public string $alias,
        public Venue $venue,
    ) {
    }
}
