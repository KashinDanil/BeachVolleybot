<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather\Location;

use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use BeachVolleybot\Weather\Location\Venue;
use BeachVolleybot\Weather\Location\VenueDirectory;
use PHPUnit\Framework\TestCase;

final class VenueDirectoryTest extends TestCase
{
    private Venue $shortName;
    private Venue $longName;
    private VenueDirectory $directory;

    protected function setUp(): void
    {
        $this->shortName = new Venue('Mar Bella', new LocationCoordinates(41.400, 2.216), ['Platja de la Mar Bella', 'Мар Белья']);
        $this->longName = new Venue('Nova Mar Bella', new LocationCoordinates(41.405, 2.224), []);

        $this->directory = new VenueDirectory([$this->shortName, $this->longName]);
    }

    public function testFindsVenueByItsName(): void
    {
        $this->assertSame($this->shortName, $this->directory->findInTitle('Mar Bella 18:30'));
    }

    public function testFindsVenueByAlias(): void
    {
        $this->assertSame($this->shortName, $this->directory->findInTitle('Platja de la Mar Bella 18:30'));
    }

    public function testMatchIgnoresCase(): void
    {
        $this->assertSame($this->shortName, $this->directory->findInTitle('MAR BELLA 18:30'));
        $this->assertSame($this->shortName, $this->directory->findInTitle('мар белья 18:30'));
    }

    public function testMatchesAliasGluedToSurroundingText(): void
    {
        // The critical free-text case: weekday, date, time and a parenthetical comment glued to
        // the venue name. Substring matching still finds it.
        $title = 'вторник 21 10:00 Mar Bella(давно не играли там)';

        $this->assertSame($this->shortName, $this->directory->findInTitle($title));
    }

    public function testLongestAliasWinsWhenAShorterOneIsASubstring(): void
    {
        // "Nova Mar Bella" contains "Mar Bella" — longest-alias-first ordering prevents the
        // shorter match from shadowing the more specific venue.
        $this->assertSame($this->longName, $this->directory->findInTitle('Nova Mar Bella 18:00'));
    }

    public function testReturnsNullWhenTitleMentionsNoVenue(): void
    {
        $this->assertNull($this->directory->findInTitle('Friday 18:00'));
    }

    public function testReturnsNullForEmptyTitle(): void
    {
        $this->assertNull($this->directory->findInTitle(''));
    }

    public function testFindByNameReturnsTheExactVenue(): void
    {
        $this->assertSame($this->longName, $this->directory->findByName('Nova Mar Bella'));
    }

    public function testFindByNameIgnoresCase(): void
    {
        $this->assertSame($this->shortName, $this->directory->findByName('mar bella'));
    }

    public function testFindByNameDoesNotMatchOnSubstring(): void
    {
        // Unlike findInTitle, findByName is an exact-name lookup: a partial name resolves nothing.
        $this->assertNull($this->directory->findByName('Mar'));
    }

    public function testFindByNameReturnsNullForUnknownVenue(): void
    {
        $this->assertNull($this->directory->findByName('Atlantis'));
    }

    public function testAllReturnsTheVenuesItWasBuiltFrom(): void
    {
        $this->assertSame([$this->shortName, $this->longName], $this->directory->all());
    }

    public function testEmptyDirectoryMatchesNothing(): void
    {
        $directory = new VenueDirectory([]);

        $this->assertSame([], $directory->all());
        $this->assertNull($directory->findInTitle('Mar Bella 18:30'));
    }
}
