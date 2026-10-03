<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\Extractors;

use BeachVolleybot\Localization\InputVocabulary;
use BeachVolleybot\Validator\Rules\Game\MinimumPlayersPerNetRule;

final class PlayersPerNetExtractor implements ExtractorInterface
{
    private static ?string $pattern = null;

    private static ?string $countFirstPattern = null;

    private static ?string $netFirstPattern = null;

    /** Either word order; extract() and resolvePlayersPerNet() prefer count-first wherever it appears. */
    public static function pattern(): string
    {
        // Branch reset keeps the count in group 1 whichever word order matched.
        return self::$pattern ??= '/(*UCP)(?|' . self::countFirstBody() . '|' . self::netFirstBody() . ')/iu';
    }

    /** Returns the raw matched span even below the minimum — resolvePlayersPerNet() is what nullifies that, not the pattern. */
    public static function extract(string $text): ?string
    {
        return self::match($text)[0] ?? null;
    }

    public static function resolvePlayersPerNet(string $text): ?int
    {
        $matches = self::match($text);

        if (null === $matches) {
            return null;
        }

        $playersPerNet = (int) $matches[1];

        return new MinimumPlayersPerNetRule($playersPerNet)->isValid() ? $playersPerNet : null;
    }

    /** @return array<int, string>|null */
    private static function match(string $text): ?array
    {
        if (1 === preg_match(self::countFirstPattern(), $text, $matches)) {
            return $matches;
        }

        if (1 === preg_match(self::netFirstPattern(), $text, $matches)) {
            return $matches;
        }

        return null;
    }

    private static function countFirstPattern(): string
    {
        return self::$countFirstPattern ??= '/(*UCP)' . self::countFirstBody() . '/iu';
    }

    private static function netFirstPattern(): string
    {
        return self::$netFirstPattern ??= '/(*UCP)' . self::netFirstBody() . '/iu';
    }

    /** "6 человек максимум на сетку" */
    private static function countFirstBody(): string
    {
        return
            // Not the tail of a number that already means something else: a time, a date, a range.
            '(?<![\d:.\-\/–—])'
            . '\b(\d{1,2})\s*'
            . '(?:' . self::words(InputVocabulary::slotNouns()) . ')\.?\s+'
            . self::optionalLimitWordPattern()
            . '(?:' . self::words(InputVocabulary::perPrepositions()) . ')\s+'
            . self::netPattern();
    }

    /** "на сетку 6 человек", "per net: 6 players" */
    private static function netFirstBody(): string
    {
        return '\b(?:' . self::words(InputVocabulary::perPrepositions()) . ')\s+'
            . self::netPattern()
            . '\s*[:\-–—]?\s*'
            . self::optionalLimitWordPattern()
            . '\b(\d{1,2})\s*'
            . '(?:' . self::words(InputVocabulary::slotNouns()) . ')\b';
    }

    private static function netPattern(): string
    {
        return '(?:(?:1|' . self::words(InputVocabulary::netQuantifiers()) . ')\s+)?'
            . '(?:' . self::words(InputVocabulary::netNouns()) . ')\b';
    }

    private static function optionalLimitWordPattern(): string
    {
        return '(?:(?:' . self::words(InputVocabulary::limitWords()) . ')\.?\s+)?';
    }

    /** @param list<string> $words */
    private static function words(array $words): string
    {
        return implode('|', $words);
    }
}
