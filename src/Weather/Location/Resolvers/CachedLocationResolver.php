<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location\Resolvers;

use BeachVolleybot\Weather\Location\Cache\LocationCacheManager;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;

final readonly class CachedLocationResolver implements LocationResolverInterface
{
    public function __construct(
        private LocationCacheManager $cache = new LocationCacheManager(),
    ) {
    }

    public function resolve(string $query): ?LocationCoordinates
    {
        return $this->cache->find($query)?->coordinates;
    }
}
