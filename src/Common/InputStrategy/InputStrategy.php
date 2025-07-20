<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\InputStrategy;

abstract class InputStrategy
{
    protected string $secretToken;
    protected string $payload;

    public function getSecretToken(): string
    {
        return $this->secretToken;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }
}
