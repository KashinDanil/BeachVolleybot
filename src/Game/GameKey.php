<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

/**
 * The stable per-game identity token embedded in a game message's meta-button.
 *
 * Inline-created games reuse the Telegram inline_query id. A game created from a
 * chat message derives its key from the triggering message's (chat_id,
 * message_id) — globally unique, so re-processing the same message can never mint
 * a second game. The "msg:" prefix keeps it disjoint from inline_query ids.
 */
final class GameKey
{
    private const string MESSAGE_PREFIX = 'msg';

    private const string EPHEMERAL_PREFIX = 'eph';

    public static function fromMessage(int $chatId, int $messageId): string
    {
        return sprintf('%s:%d:%d', self::MESSAGE_PREFIX, $chatId, $messageId);
    }

    /**
     * A game built through the /new_game wizard's ephemeral message keys off the
     * wizard message's (chat_id, ephemeral_message_id) — unique per wizard session,
     * so re-processing the final tap can never mint a second game. The "eph:" prefix
     * keeps it disjoint from message and inline_query keys.
     */
    public static function fromEphemeral(int $chatId, int $ephemeralMessageId): string
    {
        return sprintf('%s:%d:%d', self::EPHEMERAL_PREFIX, $chatId, $ephemeralMessageId);
    }
}
