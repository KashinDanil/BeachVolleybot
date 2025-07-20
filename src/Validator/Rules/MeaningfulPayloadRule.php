<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Errors\ValidationError;

readonly class MeaningfulPayloadRule implements RuleInterface
{
    public function __construct(private string|bool $payload)
    {
    }

    public function isValid(): bool
    {
        return !empty($this->payload)
            && is_string($this->payload)
            && json_validate($this->payload);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(
            'Wrong payload',
            ['payload' => $this->payload]
        );
    }
}
