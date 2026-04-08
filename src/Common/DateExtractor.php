<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

final class DateExtractor
{
    public const string PATTERN = '/\b\d{1,2}\.\d{2}(?:\.\d{2,4})?\b/';

    public static function extract(string $text): ?string
    {
        if (1 !== preg_match(self::PATTERN, $text, $matches)) {
            return null;
        }

        return $matches[0];
    }
}
