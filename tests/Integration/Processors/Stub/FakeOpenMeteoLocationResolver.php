<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Stub;

use BeachVolleybot\Weather\Location\Cache\LocationCacheManager;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use BeachVolleybot\Weather\Location\Resolvers\LocationResolverInterface;

/**
 * Test double for OpenMeteoLocationResolver: mirrors cache-first + write-back
 * semantics but substitutes HTTP with a canned-response lookup.
 */
final class FakeOpenMeteoLocationResolver implements LocationResolverInterface
{
    /** @var list<string> */
    public array $queries = [];

    /** @var array<string, ?LocationCoordinates> */
    public array $responses = [];

    public function __construct(
        private readonly LocationCacheManager $cache,
    ) {
    }

    public function resolve(string $query): ?LocationCoordinates
    {
        $row = $this->cache->find($query);
        if (null !== $row) {
            return $row->coordinates;
        }

        $this->queries[] = $query;
        $coordinates = $this->responses[$query] ?? null;
        $this->cache->remember($query, $coordinates);

        return $coordinates;
    }
}
