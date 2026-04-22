<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Stub;

use BeachVolleybot\Weather\GeocodingClientInterface;
use BeachVolleybot\Weather\LocationCoordinates;

final class FakeGeocodingClient implements GeocodingClientInterface
{
    /** @var list<string> */
    public array $queries = [];

    /** @var array<string, ?LocationCoordinates> */
    public array $responses = [];

    public function resolve(string $query): ?LocationCoordinates
    {
        $this->queries[] = $query;

        return $this->responses[$query] ?? null;
    }
}