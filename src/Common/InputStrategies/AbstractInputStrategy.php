<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\InputStrategies;

abstract class AbstractInputStrategy
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

    abstract public function getRequestMethod(): ?string;
}
