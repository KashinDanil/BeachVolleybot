<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\Extractors;

use BeachVolleybot\Localization\InputVocabulary;
use BeachVolleybot\Validator\Rules\Game\MinimumPlayersPerNetRule;

final class PlayersPerNetExtractor implements ExtractorInterface
{
    private static ?string $pattern = null;

    public static function pattern(): string
    {
        return self::$pattern ??= '/(*UCP)'
            // Not the tail of a number that already means something else: a time, a date, a range.
            . '(?<![\d:.\-\/–—])'
            . '\b(\d{1,2})\s*'
            . '(?:' . self::words(InputVocabulary::slotNouns()) . ')\s+'
            . '(?:' . self::words(InputVocabulary::perPrepositions()) . ')\s+'
            . '(?:(?:1|' . self::words(InputVocabulary::netQuantifiers()) . ')\s+)?'
            . '(?:' . self::words(InputVocabulary::netNouns()) . ')\b/iu';
    }

    /** Returns the raw matched span even below the minimum — resolvePlayersPerNet() is what nullifies that, not the pattern. */
    public static function extract(string $text): ?string
    {
        if (1 !== preg_match(self::pattern(), $text, $matches)) {
            return null;
        }

        return $matches[0];
    }

    public static function resolvePlayersPerNet(string $text): ?int
    {
        if (1 !== preg_match(self::pattern(), $text, $matches)) {
            return null;
        }

        $playersPerNet = (int) $matches[1];

        return new MinimumPlayersPerNetRule($playersPerNet)->isValid() ? $playersPerNet : null;
    }

    /** @param list<string> $words */
    private static function words(array $words): string
    {
        return implode('|', $words);
    }
}
