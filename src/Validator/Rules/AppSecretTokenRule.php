<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Errors\ValidationError;

readonly class AppSecretTokenRule implements RuleInterface
{
    public function __construct(private string $token)
    {
    }

    public function isValid(): bool
    {
        return password_verify($this->token, APP_TOKEN_HASH);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(
            'Provided token is invalid',
            ['token' => $this->token]
        );
    }
}
