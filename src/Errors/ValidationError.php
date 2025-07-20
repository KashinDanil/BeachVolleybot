<?php

declare(strict_types=1);

namespace BeachVolleybot\Errors;

readonly class ValidationError implements ErrorInterface
{
    public function __construct(private string $message, private array $data = [])
    {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
