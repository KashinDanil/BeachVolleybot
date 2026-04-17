<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

readonly class ParsedDate
{
    public function __construct(
        public int $day,
        public int $month,
        public ?int $year = null,
    ) {
    }
}
