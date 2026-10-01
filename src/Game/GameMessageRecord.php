<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Database\Timestamp;
use BeachVolleybot\Telegram\Messages\MessageAddress;
use DateTimeImmutable;

/**
 * A place where a game is posted: an inline message (inline_message_id) or a normal
 * chat message (chat_id + message_id). One row of game_messages; either identity is set.
 */
final readonly class GameMessageRecord
{
    public function __construct(
        public int $gameId,
        public ?int $chatId,
        public ?int $messageId,
        public ?string $inlineMessageId,
        public ?string $inlineQueryId,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int)$row['game_id'],
            isset($row['chat_id']) ? (int)$row['chat_id'] : null,
            isset($row['message_id']) ? (int)$row['message_id'] : null,
            $row['inline_message_id'] ?? null,
            $row['inline_query_id'] ?? null,
            Timestamp::parse((string)$row['created_at']),
        );
    }

    public function address(): MessageAddress
    {
        if (null !== $this->inlineMessageId) {
            return MessageAddress::inline($this->inlineMessageId);
        }

        return MessageAddress::chat($this->chatId, $this->messageId);
    }
}
