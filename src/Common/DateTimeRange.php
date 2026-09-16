<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

use DateTimeImmutable;

/** A span of instants, `from` inclusive and `until` exclusive. */
final readonly class DateTimeRange
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
    ) {
    }
}
