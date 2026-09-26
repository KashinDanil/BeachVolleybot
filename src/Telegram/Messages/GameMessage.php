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
        public ?int $gameId = null,
        public ?int $chatId = null,
        public ?int $messageId = null,
        public ?string $inlineMessageId = null,
        public ?string $inlineQueryId = null,
        public ?bool $authorized = null,
    ) {
    }

    public static function fromArray(array $row): self
    {
        $authorized = null;

        if (null !== ($row['authorized'] ?? null)) {
            $authorized = 1 === (int)$row['authorized'];
        }

        if (null !== $row['inline_message_id']) {
            return new self(
                gameId: (int)$row['game_id'],
                inlineMessageId: $row['inline_message_id'],
                inlineQueryId: $row['inline_query_id'],
                authorized: $authorized,
            );
        }

        return new self(
            gameId: (int)$row['game_id'],
            chatId: (int)$row['chat_id'],
            messageId: (int)$row['message_id'],
            authorized: $authorized
        );
    }

    public function isInline(): bool
    {
        return null !== $this->inlineMessageId;
    }

    public function isBlocked(): bool
    {
        return false === $this->authorized;
    }
}
