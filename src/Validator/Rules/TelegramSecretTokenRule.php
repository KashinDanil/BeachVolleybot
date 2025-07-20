<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Errors\ValidationError;

readonly class TelegramSecretTokenRule implements RuleInterface
{
    public function __construct(private string $token)
    {
    }

    public function isValid(): bool
    {
        return password_verify($this->token, TG_BOT_WEBHOOK_TOKEN_HASH);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(
            'Provided token is invalid',
            ['token' => $this->token]
        );
    }
}
