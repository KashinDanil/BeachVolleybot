<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Weather\Forecast\Models\WeatherWindow;
use BeachVolleybot\Weather\Forecast\WeatherWindowResolver;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;
use DateTimeZone;
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
        $creationDate = new DateTimeImmutable('next friday', KnownVenues::defaultVenue()->timezone)->setTime(10, 0);
        $expectedKickoffDay = $creationDate->modify('+1 day');
        $kickoffAt = $this->makeKickoff('Bogatell Saturday 18:00', createdAt: $creationDate);

        $window = $this->resolver->windowFor($kickoffAt);

        $this->assertSame($expectedKickoffDay->format('Y-m-d') . ' 18:00:00', $window->kickoffHour->format('Y-m-d H:i:s'));
    }

    public function testFallsBackToCreationDateWhenNoDateInTitle(): void
    {
        $creationDate = new DateTimeImmutable('now', KnownVenues::defaultVenue()->timezone)->setTime(10, 0);
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

    public function testWindowSpanningSpringForwardStaysOnConsecutiveHours(): void
    {
        // Barcelona jumps 02:00 -> 03:00 on 29.03.2026, so 03:00 local is the first hour of CEST.
        $kickoffAt = $this->makeKickoff('Bogatell 29.03.2026 03:00', createdAt: new DateTimeImmutable('2026-03-20'));

        $window = $this->resolver->windowFor($kickoffAt, new DateTimeImmutable('2026-03-27 12:00'));

        $this->assertSame([
            '2026-03-29 00:00',
            '2026-03-29 01:00',
            '2026-03-29 02:00',
            '2026-03-29 03:00',
            '2026-03-29 04:00',
        ], $this->hoursAsUtc($window));
    }

    public function testWindowSpanningFallBackStaysOnConsecutiveHours(): void
    {
        // Barcelona repeats 02:00 -> 03:00 on 25.10.2026, so this wall clock happens twice.
        $kickoffAt = $this->makeKickoff('Bogatell 25.10.2026 02:00', createdAt: new DateTimeImmutable('2026-10-20'));

        $window = $this->resolver->windowFor($kickoffAt, new DateTimeImmutable('2026-10-23 12:00'));

        $this->assertSame([
            '2026-10-24 23:00',
            '2026-10-25 00:00',
            '2026-10-25 01:00',
            '2026-10-25 02:00',
            '2026-10-25 03:00',
        ], $this->hoursAsUtc($window));
    }

    public function testWindowHoldsWhileNowIsInsideTheRepeatedFallBackHour(): void
    {
        // 00:30Z and 01:30Z are both 02:30 in Barcelona on 25.10.2026 — that hour runs twice.
        $kickoffAt = $this->makeKickoff('Bogatell 25.10.2026 06:00', createdAt: new DateTimeImmutable('2026-10-20'));

        foreach (['2026-10-25 00:30', '2026-10-25 01:30'] as $nowInUtc) {
            $window = $this->resolver->windowFor($kickoffAt, $this->instant($nowInUtc));

            $this->assertSame([
                '2026-10-25 04:00',
                '2026-10-25 05:00',
                '2026-10-25 06:00',
                '2026-10-25 07:00',
                '2026-10-25 08:00',
            ], $this->hoursAsUtc($window), "now={$nowInUtc}Z");
        }
    }

    public function testWindowHoldsWhileNowIsInsideTheSpringForwardJump(): void
    {
        // Barcelona has no 02:30 on 29.03.2026: 00:30Z is 01:30 CET, and 01:00Z is the jump to 03:00 CEST.
        $kickoffAt = $this->makeKickoff('Bogatell 29.03.2026 06:00', createdAt: new DateTimeImmutable('2026-03-20'));

        foreach (['2026-03-29 00:30', '2026-03-29 01:00'] as $nowInUtc) {
            $window = $this->resolver->windowFor($kickoffAt, $this->instant($nowInUtc));

            $this->assertSame([
                '2026-03-29 03:00',
                '2026-03-29 04:00',
                '2026-03-29 05:00',
                '2026-03-29 06:00',
                '2026-03-29 07:00',
            ], $this->hoursAsUtc($window), "now={$nowInUtc}Z");
        }
    }

    /** A wall clock cannot name an instant inside a transition, so these read as UTC. */
    private function instant(string $utcWallClock): DateTimeImmutable
    {
        return new DateTimeImmutable($utcWallClock, new DateTimeZone('UTC'));
    }

    /** @return list<string> */
    private function hoursAsUtc(WeatherWindow $window): array
    {
        return array_map(
            static fn(DateTimeImmutable $hour): string => $hour->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'),
            $window->hours,
        );
    }

    private function makeKickoff(string $title, DateTimeImmutable $createdAt): DateTimeImmutable
    {
        return GameDateTimeResolver::resolveOrFail($title, $createdAt);
    }
}
