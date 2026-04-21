<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

interface GeocodingClientInterface
{
    public function resolve(string $query): ?LocationCoordinates;
}
