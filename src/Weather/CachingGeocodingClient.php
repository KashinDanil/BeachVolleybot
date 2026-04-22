<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

final readonly class CachingGeocodingClient implements GeocodingClientInterface
{
    public function __construct(
        private GeocodingClientInterface $inner,
        private GeocodingCacheManager $cache,
    ) {
    }

    public function resolve(string $query): ?LocationCoordinates
    {
        $cachedRow = $this->cache->find($query);

        if (null !== $cachedRow) {
            return $cachedRow->coordinates;
        }

        $coordinates = $this->inner->resolve($query);
        $this->cache->save($query, $coordinates);

        return $coordinates;
    }
}