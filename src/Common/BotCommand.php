<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

final class BotCommand
{
    public static function matches(string $command, ?string $text): bool
    {
        return $command === $text || self::mention($command) === $text;
    }

    public static function mention(string $command): string
    {
        return $command . '@' . BOT_USERNAME;
    }
}
