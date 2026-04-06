<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

final class TimeExtractor
{
    private const string PATTERN = '/\b(\d{1,2})[:](\d{2})\b/';

    private const string NORMALIZED_FORMAT = '%02d:%02d';

    public static function extract(string $text): ?string
    {
        if (1 !== preg_match(self::PATTERN, $text, $matches)) {
            return null;
        }

        return sprintf(self::NORMALIZED_FORMAT, (int) $matches[1], (int) $matches[2]);
    }

    public static function extractRaw(string $text): ?string
    {
        if (1 !== preg_match(self::PATTERN, $text, $matches)) {
            return null;
        }

        return $matches[0];
    }

    public static function normalize(string $text): string
    {
        if (1 !== preg_match(self::PATTERN, $text, $matches)) {
            return $text;
        }

        $normalized = sprintf(self::NORMALIZED_FORMAT, (int) $matches[1], (int) $matches[2]);

        return str_replace($matches[0], $normalized, $text);
    }
}