<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages;

/** Where Telegram finds a posted message: an inline message, or a message in a chat. */
final readonly class MessageAddress
{
    private function __construct(
        public ?int $chatId = null,
        public ?int $messageId = null,
        public ?string $inlineMessageId = null,
    ) {
    }

    public static function inline(string $inlineMessageId): self
    {
        return new self(inlineMessageId: $inlineMessageId);
    }

    public static function chat(int $chatId, int $messageId): self
    {
        return new self(chatId: $chatId, messageId: $messageId);
    }

    public function isInline(): bool
    {
        return null !== $this->inlineMessageId;
    }
}
