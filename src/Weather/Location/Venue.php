<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location;

use BeachVolleybot\Weather\Location\Models\LocationCoordinates;

/**
 * A place games are played at: the name we show and the spellings we recognise it by.
 */
final readonly class Venue
{
    /** @param list<string> $aliases spellings other than the name — translations, transliterations, full Catalan names */
    public function __construct(
        private(set) string $name,
        private(set) LocationCoordinates $coordinates,
        private(set) array $aliases = [],
    ) {
    }
}
