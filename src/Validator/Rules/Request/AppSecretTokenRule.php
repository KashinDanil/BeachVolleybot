<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules\Request;

use BeachVolleybot\Errors\ValidationError;
use BeachVolleybot\Validator\Rules\RuleInterface;

readonly class AppSecretTokenRule implements RuleInterface
{
    private const string ERROR_MESSAGE = 'Provided token is invalid';

    public function __construct(private string $token)
    {
    }

    public function isValid(): bool
    {
        return password_verify($this->token, APP_TOKEN_HASH);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['secret_token' => $this->token]);
    }
}
