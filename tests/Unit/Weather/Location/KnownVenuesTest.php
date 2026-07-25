<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather\Location;

use BeachVolleybot\Weather\Location\KnownVenues;
use PHPUnit\Framework\TestCase;

/**
 * Covers the catalog data. The matching rules themselves live in VenueDirectoryTest.
 */
final class KnownVenuesTest extends TestCase
{
    public function testFindsVenueInARealWorldTitle(): void
    {
        $venue = KnownVenues::findInTitle('вторник 21 10:00 Somorrostro(давно не играли там)');

        $this->assertNotNull($venue);
        $this->assertSame('Somorrostro', $venue->name);
        $this->assertSame(41.383, $venue->coordinates->latitude);
        $this->assertSame(2.198, $venue->coordinates->longitude);
    }

    public function testFindsVenueByCatalanAlias(): void
    {
        $venue = KnownVenues::findInTitle('Platja del Bogatell en la tarde');

        $this->assertNotNull($venue);
        $this->assertSame('Bogatell', $venue->name);
    }

    public function testFindsVenueByRussianAlias(): void
    {
        $venue = KnownVenues::findInTitle('Пляж Богатель 18:30');

        $this->assertNotNull($venue);
        $this->assertSame('Bogatell', $venue->name);
    }

    public function testReturnsNullWhenNoKnownVenueInTitle(): void
    {
        $this->assertNull(KnownVenues::findInTitle('Friday 18:00'));
    }

    public function testReturnsNullForEmptyString(): void
    {
        $this->assertNull(KnownVenues::findInTitle(''));
    }

    public function testExposesEveryCatalogVenue(): void
    {
        $this->assertCount(17, KnownVenues::all());
    }

    public function testLookupsReturnTheCatalogInstances(): void
    {
        $bogatell = KnownVenues::findInTitle('Bogatell 18:30');

        $this->assertContains($bogatell, KnownVenues::all(), 'Lookups must hand back the shared Venue instances');
    }

    public function testEveryNameAndAliasResolvesBackToItsOwnVenue(): void
    {
        // Guards against a new venue whose spelling is swallowed by another venue's longer alias,
        // and against the same alias being listed under two venues.
        foreach (KnownVenues::all() as $venue) {
            foreach ([$venue->name, ...$venue->aliases] as $term) {
                $this->assertSame(
                    $venue,
                    KnownVenues::findInTitle($term),
                    "'{$term}' should resolve to {$venue->name}",
                );
            }
        }
    }

    public function testNamesAndAliasesDoNotMixLatinAndCyrillicLetters(): void
    {
        // A Cyrillic "о" and a Latin "o" look identical in the source but never match each other.
        foreach (KnownVenues::all() as $venue) {
            foreach ([$venue->name, ...$venue->aliases] as $term) {
                $mixesScripts = 1 === preg_match('/\p{Latin}/u', $term)
                    && 1 === preg_match('/\p{Cyrillic}/u', $term);

                $this->assertFalse($mixesScripts, "'{$term}' mixes Latin and Cyrillic letters — likely a homoglyph typo");
            }
        }
    }

    public function testCoordinatesAreStoredAtRoundedPrecision(): void
    {
        foreach (KnownVenues::all() as $venue) {
            $this->assertEquals(
                $venue->coordinates->rounded(),
                $venue->coordinates,
                "{$venue->name} coordinates must be stored rounded, so cached forecasts are shared",
            );
        }
    }
}
