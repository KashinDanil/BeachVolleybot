<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Incoming;

readonly class TelegramInlineKeyboardButton
{
    public function __construct(
        public string $text,
        public ?string $callbackData = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            text: $data['text'],
            callbackData: $data['callback_data'] ?? null,
        );
    }
}
