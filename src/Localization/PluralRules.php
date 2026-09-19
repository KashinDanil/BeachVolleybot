<?php

declare(strict_types=1);

namespace BeachVolleybot\Localization;

use DanilKashin\Localization\Language;

/**
 * Maps a count to the CLDR plural category it falls into, for a given language — the count
 * stays out of the translation files entirely; only the resulting word per category lives
 * there. Adding a language means adding one arm here, never touching an existing one.
 */
final readonly class PluralRules
{
    public function __construct(
        private string $language,
        private int $count,
    ) {
    }

    public function category(): PluralCategory
    {
        return match ($this->language) {
            Language::RU => $this->russian(),
            default      => $this->oneThenOther(),
        };
    }

    /** English, Spanish, and the CLDR default for any language not listed above: singular at exactly 1, otherwise the general form. */
    private function oneThenOther(): PluralCategory
    {
        return 1 === $this->count ? PluralCategory::One : PluralCategory::Other;
    }

    /**
     * CLDR "ru": "1 игрок" (one), "2/3/4 игрока" (few), "5 игроков" (many) — and the
     * -надцать teens (11-14) always take "many", even though 11 ends in 1 like "one" would.
     */
    private function russian(): PluralCategory
    {
        $lastDigit = $this->count % 10;
        $lastTwoDigits = $this->count % 100;

        if (1 === $lastDigit && 11 !== $lastTwoDigits) {
            return PluralCategory::One;
        }

        if (2 <= $lastDigit && 4 >= $lastDigit && !(12 <= $lastTwoDigits && 14 >= $lastTwoDigits)) {
            return PluralCategory::Few;
        }

        return PluralCategory::Many;
    }
}
