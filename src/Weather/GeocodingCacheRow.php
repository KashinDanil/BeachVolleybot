<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use DateTimeImmutable;

final readonly class GeocodingCacheRow
{
    public function __construct(
        public ?LocationCoordinates $coordinates,
        public DateTimeImmutable $fetchedAt,
    ) {
    }
}
