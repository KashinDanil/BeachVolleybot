<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

readonly class TelegramChat
{
    public function __construct(
        public int $id,
        public string $type,
        public ?string $title = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            title: $data['title'] ?? null,
        );
    }
}