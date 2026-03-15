<?php

declare(strict_types=1);

namespace BeachVolleybot\Queue;

readonly class QueueMessage
{
    public function __construct(
        public array $payload,
    ) {
    }
}