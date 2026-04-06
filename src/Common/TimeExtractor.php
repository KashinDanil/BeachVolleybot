<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

final class TimeExtractor
{
    private const string PATTERN = '/\b(\d{1,2})[:](\d{2})\b/';

    public static function extract(string $text): ?string
    {
        if (1 !== preg_match(self::PATTERN, $text, $matches)) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
    }

    public static function extractRaw(string $text): ?string
    {
        if (1 !== preg_match(self::PATTERN, $text, $matches)) {
            return null;
        }

        return $matches[0];
    }
}