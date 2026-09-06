<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game;

use BeachVolleybot\Game\ParsedTitle;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ParsedTitleTest extends TestCase
{
    private const string CREATED_AT = '2026-08-12 09:00:00';

    public function testResolvesKickoffAndVenue(): void
    {
        $parsedTitle = $this->resolve('Somorrostro 31.12.2099 18:00');

        $this->assertSame('2099-12-31 18:00:00', $parsedTitle->kickoffAt->format('Y-m-d H:i:s'));
        $this->assertSame('Somorrostro', $parsedTitle->venueName);
    }

    public function testResolvesVenueFromAlias(): void
    {
        $this->assertSame('Barceloneta', $this->resolve('Барселонета 31.12.2099 18:00')->venueName);
    }

    public function testVenueIsNullWhenTitleNamesNone(): void
    {
        $this->assertNull($this->resolve('Some other beach 31.12.2099 18:00')->venueName);
    }

    public function testFallsBackToCreationDayWhenTitleCarriesNoDate(): void
    {
        $this->assertSame('2026-08-12 16:00:00', $this->resolve('Today at 16:00')->kickoffAt->format('Y-m-d H:i:s'));
    }

    public function testAnchorsBareWeekdayOnCreationDate(): void
    {
        // 2026-08-12 is a Wednesday, so the next Saturday is the 15th.
        $this->assertSame('2026-08-15 10:00:00', $this->resolve('Saturday 10:00')->kickoffAt->format('Y-m-d H:i:s'));
    }

    public function testRejectsTitleWithoutTime(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolve('Just a game');
    }

    private function resolve(string $title): ParsedTitle
    {
        return ParsedTitle::parse($title, new DateTimeImmutable(self::CREATED_AT));
    }
}
