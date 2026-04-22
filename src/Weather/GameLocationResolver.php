<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use BeachVolleybot\Common\Extractors\VenueExtractor;
use BeachVolleybot\Game\Models\GameInterface;

final readonly class GameLocationResolver
{
    public function __construct(
        private GeocodingClientInterface $geocodingClient,
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

        return $this->geocodingClient->resolve($query);
    }
}
