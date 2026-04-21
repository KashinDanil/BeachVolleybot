<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common\Extractors;

use BeachVolleybot\Common\Extractors\VenueExtractor;
use PHPUnit\Framework\TestCase;

final class VenueExtractorTest extends TestCase
{
    // --- simple stripping: non-venue parts are removed ---

    public function testStripsTimeFromSingleWordVenue(): void
    {
        $this->assertSame('Bogatell', VenueExtractor::extract('Bogatell 18:30'));
    }

    public function testStripsTimeAndWeekday(): void
    {
        $this->assertSame('Bogatell', VenueExtractor::extract('Bogatell 18:30 Saturday'));
    }

    public function testPreservesMultiWordVenue(): void
    {
        $this->assertSame('Playa Bogatell', VenueExtractor::extract('Playa Bogatell 18:30'));
    }

    public function testKeepsGeoEmoji(): void
    {
        $this->assertSame('🏖️ Bogatell', VenueExtractor::extract('🏖️ Bogatell 18:30'));
    }

    public function testStripsRussianWeekday(): void
    {
        $this->assertSame('Bogatell', VenueExtractor::extract('Bogatell 18:30 суббота'));
    }

    public function testStripsSpanishWeekday(): void
    {
        $this->assertSame('Bogatell', VenueExtractor::extract('Bogatell 18:30 sábado'));
    }

    public function testStripsBotMention(): void
    {
        $this->assertSame('Bogatell', VenueExtractor::extract('@dev_beach_volleybot Bogatell 18:30 Saturday'));
    }

    public function testStripsHashtags(): void
    {
        $this->assertSame('Bogatell', VenueExtractor::extract('#beach Bogatell 18:30'));
    }

    public function testStripsNumericDate(): void
    {
        $this->assertSame('Bogatell', VenueExtractor::extract('Bogatell 18:30 15.11.2026'));
    }

    public function testStripsTextualDate(): void
    {
        $this->assertSame('Bogatell', VenueExtractor::extract('Bogatell 18:30 15 november'));
    }

    // --- connector words are stripped but surrounding words are joined ---

    public function testStripsEnglishConnectorAndJoinsSurroundingWords(): void
    {
        $this->assertSame('Volleyball Bogatell', VenueExtractor::extract('Volleyball at Bogatell 18:30 Saturday'));
    }

    public function testStripsSpanishConnectorAndJoinsSurroundingWords(): void
    {
        $this->assertSame('Volleyball Bogatell', VenueExtractor::extract('Volleyball en Bogatell 18:30'));
    }

    public function testStripsRussianConnectorAndJoinsSurroundingWords(): void
    {
        $this->assertSame('Волейбол Богатель', VenueExtractor::extract('Волейбол на Богатель 18:30'));
    }

    // --- whitespace normalization ---

    public function testCollapsesConsecutiveWhitespaceFromMultipleStrips(): void
    {
        // Date and time both stripped adjacent → no double-spaces in the result.
        $this->assertSame('Bogatell', VenueExtractor::extract('Bogatell 15.11.2026 18:30'));
    }

    public function testTrimsLeadingAndTrailingWhitespace(): void
    {
        $this->assertSame('Bogatell', VenueExtractor::extract('   Bogatell   '));
    }

    // --- null paths: nothing left after stripping ---

    public function testReturnsNullForEmptyInput(): void
    {
        $this->assertNull(VenueExtractor::extract(''));
    }

    public function testReturnsNullForOnlyDate(): void
    {
        $this->assertNull(VenueExtractor::extract('15.11.2026'));
    }

    public function testReturnsNullForOnlyTime(): void
    {
        $this->assertNull(VenueExtractor::extract('18:30'));
    }

    public function testReturnsNullForOnlyWeekday(): void
    {
        $this->assertNull(VenueExtractor::extract('Saturday'));
    }

    public function testReturnsNullForDateAndTimeCombined(): void
    {
        $this->assertNull(VenueExtractor::extract('15.11.2026 18:30 Saturday'));
    }

    public function testReturnsNullForWhitespaceOnly(): void
    {
        $this->assertNull(VenueExtractor::extract('   '));
    }
}