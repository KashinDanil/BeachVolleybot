<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

final class DayOfWeekExtractor
{
    public const string PATTERN = '/\b(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\b/i';

    public static function extract(string $text): ?string
    {
        if (1 !== preg_match(self::PATTERN, $text, $matches)) {
            return null;
        }

        return $matches[0];
    }
}
