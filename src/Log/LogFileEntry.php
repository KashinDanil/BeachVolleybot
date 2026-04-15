<?php

declare(strict_types=1);

namespace BeachVolleybot\Log;

final readonly class LogFileEntry
{
    public function __construct(
        public string $filename,
        public int $size,
    ) {
    }
}