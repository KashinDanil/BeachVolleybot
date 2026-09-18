<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common\Extractors;

use BeachVolleybot\Common\Extractors\PlayersPerNetExtractor;
use PHPUnit\Framework\TestCase;

final class PlayersPerNetExtractorTest extends TestCase
{
    // --- English ---

    public function testResolvesSixSpotsPerNet(): void
    {
        $this->assertSame(6, PlayersPerNetExtractor::resolvePlayersPerNet('Beach 6 spots per net 18:00'));
    }

    public function testResolvesWithLeadingLimitWordIgnored(): void
    {
        $this->assertSame(6, PlayersPerNetExtractor::resolvePlayersPerNet('Beach limit 6 slots per court 18:00'));
    }

    // --- Russian ---

    public function testResolvesSixMestNaSetku(): void
    {
        $this->assertSame(6, PlayersPerNetExtractor::resolvePlayersPerNet('Игра 6 мест на сетку 18:00'));
    }

    public function testResolvesFourMestaInflection(): void
    {
        $this->assertSame(4, PlayersPerNetExtractor::resolvePlayersPerNet('Игра 4 места на сетку 18:00'));
    }

    public function testResolvesTwentyOneMestoInflection(): void
    {
        $this->assertSame(21, PlayersPerNetExtractor::resolvePlayersPerNet('Игра 21 место на сетку 18:00'));
    }

    public function testResolvesWithLeadingCapacityWordIgnored(): void
    {
        $this->assertSame(6, PlayersPerNetExtractor::resolvePlayersPerNet('Игра максимум 6 человек на сетку 18:00'));
    }

    public function testResolvesWithNoSpaceBeforeSlotNoun(): void
    {
        $this->assertSame(6, PlayersPerNetExtractor::resolvePlayersPerNet('Игра 6мест на сетку 18:00'));
    }

    /**
     * The natural companion to the case above: the zero-space slot noun is exactly what would
     * let a time's minutes glue onto a real slot noun and slip past the punctuation lookbehind
     * if that lookbehind were ever removed or the slot noun relaxed back to optional.
     */
    public function testDoesNotReadTimeMinutesGluedToASlotNounAsACount(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Игра 18:06мест на сетку'));
    }

    public function testResolvesCaseInsensitiveRussian(): void
    {
        $this->assertSame(6, PlayersPerNetExtractor::resolvePlayersPerNet('ИГРА 6 МЕСТ НА СЕТКУ 18:00'));
    }

    public function testResolvesSixIgrokovNaKort(): void
    {
        $this->assertSame(6, PlayersPerNetExtractor::resolvePlayersPerNet('Игра 6 игроков на корт 18:00'));
    }

    // --- Spanish ---

    public function testResolvesSixPlazasPorRed(): void
    {
        $this->assertSame(6, PlayersPerNetExtractor::resolvePlayersPerNet('Partido 6 plazas por red 18:00'));
    }

    public function testResolvesSixHuecosPorPista(): void
    {
        $this->assertSame(6, PlayersPerNetExtractor::resolvePlayersPerNet('Partido 6 huecos por pista 18:00'));
    }

    // --- not a limit ---

    public function testMissingSlotNounReturnsNull(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Beach 6 per net 18:00'));
    }

    public function testMissingSlotNounReturnsNullRussian(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Игра 6 на сетку 18:00'));
    }

    public function testMissingSlotNounReturnsNullSpanish(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Partido 6 por red 18:00'));
    }

    public function testFourOnFourNotationReturnsNull(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Играем 4 на 4 на сетке 18:00'));
    }

    public function testSixOnSixNotationReturnsNull(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Играем 6 на 6 на сетке 18:00'));
    }

    public function testTimeIsNotConfusedWithLimit(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Игра 18:30 на сетке'));
    }

    public function testHyphenTimeRangeIsNotConfusedWithLimit(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Игра 18-20 на сетке'));
    }

    public function testEmDashTimeRangeIsNotConfusedWithLimit(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Игра 18—20 на сетке'));
    }

    public function testSlashTimeRangeIsNotConfusedWithLimit(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Игра 18/20 на сетке'));
    }

    public function testShortDateIsNotConfusedWithLimit(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Пляж 31.12 на корте 18:00'));
    }

    public function testFullDateIsNotConfusedWithLimit(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Игра 11.04.26 на сетке'));
    }

    public function testDoesNotReadADatesDayGluedToASlotNounAsACount(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Игра 31.12мест на сетку'));
    }

    public function testFourDigitYearIsNotConfusedWithLimit(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Игра 2099 на сетке'));
    }

    public function testWordOrderReversedReturnsNull(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('на сетку 6 мест'));
    }

    public function testNoPhraseReturnsNull(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Игра 18:00'));
    }

    public function testFormatNotationWithoutSecondNaReturnsNull(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('Игра 4x4 18:00'));
    }

    public function testThreeDigitCountReturnsNull(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('123 мест на сетку'));
    }

    public function testEmptyStringReturnsNull(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet(''));
    }

    // --- extract() ---

    public function testExtractReturnsTheLiteralSpan(): void
    {
        $this->assertSame('6 spots per net', PlayersPerNetExtractor::extract('Beach 6 spots per net 18:00'));
    }

    public function testExtractReturnsNullWhenNothingMatches(): void
    {
        $this->assertNull(PlayersPerNetExtractor::extract('Игра 18:00'));
    }

    // --- minimum enforcement ---

    public function testBelowMinimumCountReturnsNull(): void
    {
        $this->assertNull(PlayersPerNetExtractor::resolvePlayersPerNet('2 мест на сетку'));
    }

    public function testAtMinimumCountResolves(): void
    {
        $this->assertSame(4, PlayersPerNetExtractor::resolvePlayersPerNet('4 места на сетку'));
    }
}
