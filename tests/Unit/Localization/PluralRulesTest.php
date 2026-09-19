<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Localization;

use BeachVolleybot\Localization\PluralCategory;
use BeachVolleybot\Localization\PluralRules;
use DanilKashin\Localization\Language;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PluralRulesTest extends TestCase
{
    // --- English / Spanish / anything unlisted: CLDR's one-then-other ---

    public function testEnglishIsOneOnlyAtExactlyOne(): void
    {
        $this->assertSame(PluralCategory::One, new PluralRules(Language::EN, 1)->category());
        $this->assertSame(PluralCategory::Other, new PluralRules(Language::EN, 2)->category());
        $this->assertSame(PluralCategory::Other, new PluralRules(Language::EN, 21)->category());
    }

    public function testSpanishIsOneOnlyAtExactlyOne(): void
    {
        $this->assertSame(PluralCategory::One, new PluralRules(Language::ES, 1)->category());
        $this->assertSame(PluralCategory::Other, new PluralRules(Language::ES, 21)->category());
    }

    public function testAnUnknownLanguageFallsBackToOneThenOther(): void
    {
        $this->assertSame(PluralCategory::One, new PluralRules('xx', 1)->category());
        $this->assertSame(PluralCategory::Other, new PluralRules('xx', 5)->category());
    }

    // --- Russian: one / few / many, teens always many ---

    #[DataProvider('russianCases')]
    public function testRussianCategory(int $count, PluralCategory $expected): void
    {
        $this->assertSame($expected, new PluralRules(Language::RU, $count)->category());
    }

    /** @return array<string, array{int, PluralCategory}> */
    public static function russianCases(): array
    {
        return [
            '1 → one' => [1, PluralCategory::One],
            '21 → one' => [21, PluralCategory::One],
            '31 → one' => [31, PluralCategory::One],
            '101 → one' => [101, PluralCategory::One],
            '11 → many, not one, despite ending in 1' => [11, PluralCategory::Many],
            '111 → many, not one' => [111, PluralCategory::Many],
            '2 → few' => [2, PluralCategory::Few],
            '3 → few' => [3, PluralCategory::Few],
            '4 → few' => [4, PluralCategory::Few],
            '22 → few' => [22, PluralCategory::Few],
            '104 → few' => [104, PluralCategory::Few],
            '12 → many, not few, despite ending in 2' => [12, PluralCategory::Many],
            '13 → many, not few' => [13, PluralCategory::Many],
            '14 → many, not few' => [14, PluralCategory::Many],
            '5 → many' => [5, PluralCategory::Many],
            '10 → many' => [10, PluralCategory::Many],
            '20 → many' => [20, PluralCategory::Many],
            '100 → many' => [100, PluralCategory::Many],
        ];
    }
}
