<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location\Cache;

use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeImmutable;

final readonly class CachedLocationRow
{
    public function __construct(
        public ?LocationCoordinates $coordinates,
        public DateTimeImmutable $fetchedAt,
    ) {
    }
}
