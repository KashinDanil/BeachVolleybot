<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

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
