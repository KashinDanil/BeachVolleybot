<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Weather\Forecast\WeatherWindowResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WeatherWindowResolverTest extends TestCase
{
    private WeatherWindowResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new WeatherWindowResolver();
    }

    public function testWindowContainsFiveHoursCentredOnKickoffInNearFuture(): void
    {
        $kickoffDay = new DateTimeImmutable('+3 days');
        $kickoffAt = $this->makeKickoff(
            title: 'Bogatell ' . $kickoffDay->format('d.m.Y') . ' 18:00',
            createdAt: new DateTimeImmutable(),
        );

        $window = $this->resolver->windowFor($kickoffAt);

        $this->assertSame($kickoffDay->format('Y-m-d') . ' 18:00:00', $window->kickoffHour->format('Y-m-d H:i:s'));
        $this->assertCount(5, $window->hours);
        $this->assertSame($kickoffDay->format('Y-m-d') . ' 17:00:00', $window->hours[0]->format('Y-m-d H:i:s'));
        $this->assertSame($kickoffDay->format('Y-m-d') . ' 18:00:00', $window->hours[1]->format('Y-m-d H:i:s'));
        $this->assertSame($kickoffDay->format('Y-m-d') . ' 19:00:00', $window->hours[2]->format('Y-m-d H:i:s'));
        $this->assertSame($kickoffDay->format('Y-m-d') . ' 20:00:00', $window->hours[3]->format('Y-m-d H:i:s'));
        $this->assertSame($kickoffDay->format('Y-m-d') . ' 21:00:00', $window->hours[4]->format('Y-m-d H:i:s'));
    }

    public function testResolvesDayOfWeekRelativeToCreationDate(): void
    {
        // Creation on a Friday (actual date) — "Saturday" means tomorrow from creation.
        // To keep kickoff in the future for the horizon check, the creation date is today minus 0 days.
        $creationDate = new DateTimeImmutable('next friday')->setTime(10, 0);
        $expectedKickoffDay = $creationDate->modify('+1 day');
        $kickoffAt = $this->makeKickoff('Bogatell Saturday 18:00', createdAt: $creationDate);

        $window = $this->resolver->windowFor($kickoffAt);

        $this->assertSame($expectedKickoffDay->format('Y-m-d') . ' 18:00:00', $window->kickoffHour->format('Y-m-d H:i:s'));
    }

    public function testFallsBackToCreationDateWhenNoDateInTitle(): void
    {
        $creationDate = new DateTimeImmutable()->setTime(10, 0);
        $kickoffAt = $this->makeKickoff('Bogatell 18:00', createdAt: $creationDate);

        $window = $this->resolver->windowFor($kickoffAt);

        $this->assertSame($creationDate->format('Y-m-d') . ' 18:00:00', $window->kickoffHour->format('Y-m-d H:i:s'));
    }

    public function testKickoffInPastReturnsEmptyHours(): void
    {
        // Fixed past date — reliably in the past regardless of when tests run.
        $kickoffAt = $this->makeKickoff('Bogatell 10.04.2020 12:00', createdAt: new DateTimeImmutable('2020-04-01'));

        $window = $this->resolver->windowFor($kickoffAt);

        $this->assertSame('2020-04-10 12:00:00', $window->kickoffHour->format('Y-m-d H:i:s'));
        $this->assertSame([], $window->hours);
    }

    public function testKickoffBeyondSevenDaysReturnsEmptyHours(): void
    {
        $kickoffDay = new DateTimeImmutable('+10 days');
        $kickoffAt = $this->makeKickoff(
            title: 'Bogatell ' . $kickoffDay->format('d.m.Y') . ' 18:00',
            createdAt: new DateTimeImmutable(),
        );

        $window = $this->resolver->windowFor($kickoffAt);

        $this->assertSame([], $window->hours);
    }

    public function testKickoffAtHorizonBoundaryIsIncluded(): void
    {
        // Kickoff exactly 6 days and 23 hours out — safely within the 7-day horizon.
        $kickoffDay = new DateTimeImmutable('+6 days');
        $kickoffAt = $this->makeKickoff(
            title: 'Bogatell ' . $kickoffDay->format('d.m.Y') . ' 12:00',
            createdAt: new DateTimeImmutable(),
        );

        $window = $this->resolver->windowFor($kickoffAt);

        $this->assertCount(5, $window->hours);
    }

    public function testRoundsKickoffAfterHalfPastUpToNextHour(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $kickoffAt = $this->makeKickoff(
            title: 'Bogatell ' . $kickoffDay->format('d.m.Y') . ' 18:45',
            createdAt: new DateTimeImmutable(),
        );

        $window = $this->resolver->windowFor($kickoffAt);

        $this->assertSame($kickoffDay->format('Y-m-d') . ' 19:00:00', $window->kickoffHour->format('Y-m-d H:i:s'));
    }

    public function testRoundsKickoffAtHalfPastUpToNextHour(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $kickoffAt = $this->makeKickoff(
            title: 'Bogatell ' . $kickoffDay->format('d.m.Y') . ' 18:30',
            createdAt: new DateTimeImmutable(),
        );

        $window = $this->resolver->windowFor($kickoffAt);

        $this->assertSame($kickoffDay->format('Y-m-d') . ' 19:00:00', $window->kickoffHour->format('Y-m-d H:i:s'));
    }

    public function testRoundsKickoffBeforeHalfPastDownToCurrentHour(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $kickoffAt = $this->makeKickoff(
            title: 'Bogatell ' . $kickoffDay->format('d.m.Y') . ' 18:15',
            createdAt: new DateTimeImmutable(),
        );

        $window = $this->resolver->windowFor($kickoffAt);

        $this->assertSame($kickoffDay->format('Y-m-d') . ' 18:00:00', $window->kickoffHour->format('Y-m-d H:i:s'));
    }

    private function makeKickoff(string $title, DateTimeImmutable $createdAt): DateTimeImmutable
    {
        return GameDateTimeResolver::resolveOrFail($title, $createdAt);
    }
}
