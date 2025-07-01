<?php

declare(strict_types=1);

namespace BeachVolleybot\webhook;

readonly class IncomingMessageDTO
{
    public function __construct(
        private readonly array $payload,
    ) {
    }
}
