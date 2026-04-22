<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location;

use BeachVolleybot\Common\Extractors\VenueExtractor;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Weather\Location\Models\DefaultLocationCoordinates;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use BeachVolleybot\Weather\Location\Resolvers\LocationResolverInterface;

final readonly class GameLocationResolver
{
    public function __construct(
        private LocationResolverInterface $locationResolver,
    ) {
    }

    public function resolve(GameInterface $game): LocationCoordinates
    {
        return $this->explicitCoordinates($game)
            ?? $this->venueCoordinates($game)
            ?? new DefaultLocationCoordinates();
    }

    private function explicitCoordinates(GameInterface $game): ?LocationCoordinates
    {
        return LocationCoordinates::tryParse($game->getLocation());
    }

    private function venueCoordinates(GameInterface $game): ?LocationCoordinates
    {
        $query = VenueExtractor::extract($game->getTitle());

        if (null === $query) {
            return null;
        }

        return $this->locationResolver->resolve($query);
    }
}
