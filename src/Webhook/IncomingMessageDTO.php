<?php

declare(strict_types=1);

namespace BeachVolleybot\Webhook;

readonly class IncomingMessageDTO
{
    public function __construct(
        private readonly array $payload,
    ) {
    }
}
