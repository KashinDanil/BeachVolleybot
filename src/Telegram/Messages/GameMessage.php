<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages;

/**
 * A place where a game is posted: an inline message (inline_message_id) or a normal
 * chat message (chat_id + message_id). One row of game_messages; either identity is set.
 */
final readonly class GameMessage
{
    public function __construct(
        public ?int $chatId = null,
        public ?int $messageId = null,
        public ?string $inlineMessageId = null,
        public ?string $inlineQueryId = null,
    ) {
    }

    public static function fromArray(array $row): self
    {
        if (null !== $row['inline_message_id']) {
            return new self(inlineMessageId: $row['inline_message_id'], inlineQueryId: $row['inline_query_id']);
        }

        return new self(chatId: (int)$row['chat_id'], messageId: (int)$row['message_id']);
    }

    public function isInline(): bool
    {
        return null !== $this->inlineMessageId;
    }
}
