<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Errors\ValidationError;

readonly class ValidPayloadRule implements RuleInterface
{
    private const string ERROR_MESSAGE = 'Invalid payload';

    public function __construct(private string $payload)
    {
    }

    public function isValid(): bool
    {
        return !empty($this->payload)
            && json_validate($this->payload);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['payload' => $this->payload]);
    }
}
