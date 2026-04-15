<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Incoming;

readonly class TelegramChat
{
    public const string TYPE_PRIVATE = 'private';

    public function __construct(
        public int $id,
        public string $type,
        public ?string $title = null,
    ) {
    }

    public function isPrivate(): bool
    {
        return self::TYPE_PRIVATE === $this->type;
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
