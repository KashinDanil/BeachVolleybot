<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Incoming;

readonly class TelegramInlineQuery
{
    public function __construct(
        public string $id,
        public TelegramUser $from,
        public string $query,
        public string $offset,
        public ?string $chatType = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            from: TelegramUser::fromArray($data['from']),
            query: $data['query'],
            offset: $data['offset'],
            chatType: $data['chat_type'] ?? null,
        );
    }
}
