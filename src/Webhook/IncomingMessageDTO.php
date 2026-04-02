<?php

declare(strict_types=1);

namespace BeachVolleybot\Webhook;

readonly class IncomingMessageDTO
{
    public function __construct(
        private array $payload,
    ) {
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
