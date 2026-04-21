<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\Extractors;

final class VenueExtractor implements ExtractorInterface
{
    private const array  CONNECTOR_WORDS   = ['at', 'en', 'на', 'в', 'у'];
    private const string BOT_MENTION_REGEX = '/@\w+/u';
    private const string HASHTAG_REGEX     = '/#\w+/u';
    private const string WHITESPACE_REGEX  = '/\s+/u';

    public static function pattern(): string
    {
        return '/(*UCP)\b(?:' . implode('|', self::CONNECTOR_WORDS) . ')\b/iu';
    }

    public static function extract(string $text): ?string
    {
        $stripped = self::stripNonVenueParts($text);
        $normalized = self::collapseWhitespace($stripped);

        return empty($normalized) ? null : $normalized;
    }

    private static function stripNonVenueParts(string $text): string
    {
        foreach (self::nonVenuePatterns() as $pattern) {
            $text = preg_replace($pattern, ' ', $text) ?? $text;
        }

        return $text;
    }

    /** @return list<string> */
    private static function nonVenuePatterns(): array
    {
        return [
            DateExtractor::pattern(),
            TimeExtractor::pattern(),
            DayOfWeekExtractor::pattern(),
            self::pattern(),
            self::BOT_MENTION_REGEX,
            self::HASHTAG_REGEX,
        ];
    }

    private static function collapseWhitespace(string $text): string
    {
        return trim(preg_replace(self::WHITESPACE_REGEX, ' ', $text) ?? $text);
    }
}