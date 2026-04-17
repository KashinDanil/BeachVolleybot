<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Incoming;

readonly class TelegramUser
{
    public function __construct(
        public int $id,
        public string $firstName,
        public bool $isBot = false,
        public ?string $lastName = null,
        public ?string $username = null,
        public ?string $languageCode = null,
        public ?bool $isPremium = null,
    ) {
    }

    public function isThisBot(): bool
    {
        return BOT_USERNAME === $this->username;
    }

    public function isAdmin(): bool
    {
        return in_array($this->id, ADMINS_TELEGRAM_USER_IDS, true);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            firstName: $data['first_name'],
            isBot: $data['is_bot'] ?? false,
            lastName: $data['last_name'] ?? null,
            username: $data['username'] ?? null,
            languageCode: $data['language_code'] ?? null,
            isPremium: $data['is_premium'] ?? null,
        );
    }
}
