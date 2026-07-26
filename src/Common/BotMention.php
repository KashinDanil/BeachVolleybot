<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

/**
 * Detects and strips the bot's @username mention in a plain message, which is
 * how a user asks the bot to turn a message into a game.
 */
final class BotMention
{
    public static function isPresent(string $text): bool
    {
        return 1 === preg_match('/' . self::mention() . '/i', $text);
    }

    public static function strip(string $text): string
    {
        // Consume the horizontal whitespace around the mention so removing it from
        // mid-text collapses to a single space, not a double one. Newlines (which
        // separate the date/venue/time lines of the title) are preserved.
        return trim((string)preg_replace('/[ \t]*' . self::mention() . '[ \t]*/i', ' ', $text));
    }

    private static function mention(): string
    {
        return '@' . preg_quote(BOT_USERNAME, '/') . '\b';
    }
}
