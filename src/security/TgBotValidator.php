<?php

declare(strict_types=1);

namespace BeachVolleybot\security;

readonly class TgBotValidator
{
    public function __construct(private string $token)
    {
    }

    public function validate(): bool
    {
        return password_verify($this->token, TG_BOT_WEBHOOK_TOKEN_HASH);
    }
}
