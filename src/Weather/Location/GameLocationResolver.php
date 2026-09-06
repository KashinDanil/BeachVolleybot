<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location;

use BeachVolleybot\Weather\Location\Models\DefaultLocationCoordinates;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;

final readonly class GameLocationResolver
{
    public function resolve(?string $location, ?string $venueName, string $title): LocationCoordinates
    {
        return LocationCoordinates::tryParse($location)
            ?? $this->resolveVenue($venueName, $title)?->coordinates
            ?? new DefaultLocationCoordinates();
    }

    /**
     * venue_name is itself findInTitle() at write time, so the scan adds something only for a row
     * written before the column existed, or before the catalog knew that venue.
     */
    private function resolveVenue(?string $venueName, string $title): ?Venue
    {
        if (null !== $venueName) {
            return KnownVenues::findByName($venueName);
        }

        return KnownVenues::findInTitle($title);
    }
}
