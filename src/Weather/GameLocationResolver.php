<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use BeachVolleybot\Common\Extractors\VenueExtractor;
use BeachVolleybot\Game\Models\GameInterface;

final readonly class GameLocationResolver
{
    public function __construct(
        private GeocodingCacheManager $geocodingCache,
    ) {
    }

    public function resolve(GameInterface $game): LocationCoordinates
    {
        return $this->explicitCoordinates($game)
            ?? $this->cachedVenueCoordinates($game)
            ?? new DefaultLocationCoordinates();
    }

    private function explicitCoordinates(GameInterface $game): ?LocationCoordinates
    {
        return LocationCoordinates::tryParse($game->getLocation());
    }

    private function cachedVenueCoordinates(GameInterface $game): ?LocationCoordinates
    {
        $query = VenueExtractor::extract($game->getTitle());

        if (null === $query) {
            return null;
        }

        return $this->geocodingCache->find($query)?->coordinates;
    }

    public function resolveVenueQuery(GameInterface $game): ?string
    {
        if (null !== $this->explicitCoordinates($game)) {
            return null;
        }

        return VenueExtractor::extract($game->getTitle());
    }
}
