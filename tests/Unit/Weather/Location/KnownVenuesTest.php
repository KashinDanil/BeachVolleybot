<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather\Location;

use BeachVolleybot\Weather\Location\KnownVenues;
use PHPUnit\Framework\TestCase;

final class KnownVenuesTest extends TestCase
{
    public function testMatchesSingleWordAliasInCleanTitle(): void
    {
        $coordinates = KnownVenues::findInTitle('Bogatell 18:30');

        $this->assertNotNull($coordinates);
        $this->assertSame(41.394, $coordinates->latitude);
        $this->assertSame(2.208, $coordinates->longitude);
    }

    public function testMatchesAliasSurroundedByPunctuationAndCommentary(): void
    {
        // The critical free-text case: the title has a weekday, date, time, and a parenthetical
        // comment glued to the venue name. Substring matching still finds "Somorrostro".
        $coordinates = KnownVenues::findInTitle('вторник 21 10:00 Somorrostro(давно не играли там)');

        $this->assertNotNull($coordinates);
        $this->assertSame(41.383, $coordinates->latitude);
        $this->assertSame(2.198, $coordinates->longitude);
    }

    public function testMatchesRussianAlias(): void
    {
        $coordinates = KnownVenues::findInTitle('Пляж Богатель 18:30');

        $this->assertNotNull($coordinates);
        $this->assertSame(41.394, $coordinates->latitude);
    }

    public function testMatchesCatalanAlias(): void
    {
        $coordinates = KnownVenues::findInTitle('Platja del Bogatell en la tarde');

        $this->assertNotNull($coordinates);
        $this->assertSame(41.394, $coordinates->latitude);
    }

    public function testMatchesLowercaseAlias(): void
    {
        $coordinates = KnownVenues::findInTitle('bogatell 18:30');

        $this->assertNotNull($coordinates);
        $this->assertSame(41.394, $coordinates->latitude);
    }

    public function testMatchesUppercaseAlias(): void
    {
        $coordinates = KnownVenues::findInTitle('BOGATELL 18:30');

        $this->assertNotNull($coordinates);
        $this->assertSame(41.394, $coordinates->latitude);
    }

    public function testLongestAliasWinsWhenShorterIsASubstring(): void
    {
        // "Nova Mar Bella" contains "Mar Bella" — longest-alias-first ordering prevents the
        // shorter match from shadowing the more specific venue.
        $coordinates = KnownVenues::findInTitle('Nova Mar Bella 18:00');

        $this->assertNotNull($coordinates);
        $this->assertSame(41.405, $coordinates->latitude);
        $this->assertSame(2.224, $coordinates->longitude);
    }

    public function testReturnsNullWhenNoKnownVenueInTitle(): void
    {
        $this->assertNull(KnownVenues::findInTitle('Friday 18:00'));
    }

    public function testReturnsNullForEmptyString(): void
    {
        $this->assertNull(KnownVenues::findInTitle(''));
    }
}
