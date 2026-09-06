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
            ?? $this->resolveVenue($game)?->coordinates
            ?? new DefaultLocationCoordinates();
    }

    /**
     * venue_name is itself findInTitle() at write time, so the scan adds something only for a row
     * written before the column existed, or before the catalog knew that venue.
     */
    private function resolveVenue(GameInterface $game): ?Venue
    {
        $venueName = $game->getVenueName();

        if (null !== $venueName) {
            return KnownVenues::findByName($venueName);
        }

        return KnownVenues::findInTitle($game->getTitle());
    }
}
