<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather\Schedule;

use BeachVolleybot\Weather\Schedule\WeatherRefreshLadder;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeatherRefreshLadderTest extends TestCase
{
    private const int HOUR = 3600;

    private DateTimeImmutable $now;

    private WeatherRefreshLadder $ladder;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-04-15 12:00:00');
        $this->ladder = new WeatherRefreshLadder();
    }

    /**
     * Seconds until kickoff => the rung's forecast age in seconds, straddling every boundary.
     *
     * @return list<array{int, int}>
     */
    public static function rungs(): array
    {
        return [
            [1 * self::HOUR, 1 * self::HOUR],
            [24 * self::HOUR, 1 * self::HOUR],
            [24 * self::HOUR + 1, 3 * self::HOUR],
            [48 * self::HOUR, 3 * self::HOUR],
            [48 * self::HOUR + 1, 6 * self::HOUR],
            [72 * self::HOUR, 6 * self::HOUR],
            [72 * self::HOUR + 1, 12 * self::HOUR],
            [7 * 24 * self::HOUR, 12 * self::HOUR],
        ];
    }

    #[DataProvider('rungs')]
    public function testForecastIsDueOnlyOnceItsRungHasElapsed(int $secondsUntilKickoff, int $rungSeconds): void
    {
        $kickoffAt = $this->now->modify("+$secondsUntilKickoff seconds");

        $this->assertFalse($this->ladder->isDue($this->now, $kickoffAt, $this->agedBy($rungSeconds - 1)));
        $this->assertTrue($this->ladder->isDue($this->now, $kickoffAt, $this->agedBy($rungSeconds)));
    }

    public function testForecastThatWasNeverFetchedIsDue(): void
    {
        $this->assertTrue($this->ladder->isDue($this->now, $this->now->modify('+5 days'), null));
    }

    public function testKickoffAlreadyPassedStaysOnTheTightestRung(): void
    {
        $kickoffAt = $this->now->modify('-30 minutes');

        $this->assertFalse($this->ladder->isDue($this->now, $kickoffAt, $this->agedBy(self::HOUR - 1)));
        $this->assertTrue($this->ladder->isDue($this->now, $kickoffAt, $this->agedBy(self::HOUR)));
    }

    private function agedBy(int $seconds): DateTimeImmutable
    {
        return $this->now->modify("-$seconds seconds");
    }
}
