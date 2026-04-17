<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use Closure;

final readonly class TitleSegment
{
    public function __construct(
        public int $offset,
        public int $length,
        public Closure $style,
    ) {}
}
