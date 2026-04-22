<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use DateTimeImmutable;

final readonly class CachedLocationRow
{
    public function __construct(
        public ?LocationCoordinates $coordinates,
        public DateTimeImmutable $fetchedAt,
    ) {
    }
}
