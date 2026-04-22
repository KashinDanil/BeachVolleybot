<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Weather\LocationCoordinates;
use BeachVolleybot\Weather\WeatherFormatter;
use BeachVolleybot\Weather\WeatherHour;
use BeachVolleybot\Weather\WeatherSnapshot;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeatherFormatterTest extends TestCase
{
    private WeatherFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new WeatherFormatter();
    }

    public function testReturnsNullForSnapshotWithNoHours(): void
    {
        $output = $this->formatter->format(
            new WeatherSnapshot([]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertNull($output);
    }

    // --- heading ---

    public function testHeadingEmojiReflectsKickoffHourWmoCode(): void
    {
        $output = $this->formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 18:00:00', weatherCode: 0)]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertStringStartsWith('☀️ Weather', (string) $output);
    }

    public function testHeadingFallsBackWhenKickoffHourIsNotInSnapshot(): void
    {
        $output = $this->formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 17:00:00', weatherCode: 61)]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertStringStartsWith('🌤️ Weather', (string) $output);
    }

    // --- per-hour emoji ---

    #[DataProvider('wmoBuckets')]
    public function testEachWmoBucketResolvesToExpectedEmoji(int $weatherCode, string $expectedEmoji): void
    {
        $hour = $this->weatherHour('2026-04-15 17:00:00', weatherCode: $weatherCode);
        $output = $this->formatter->format(
            new WeatherSnapshot([$hour]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 17:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertStringContainsString($expectedEmoji, (string) $output);
    }

    /** @return array<string, array{int, string}> */
    public static function wmoBuckets(): array
    {
        return [
            'clear sky'        => [0, '☀️'],
            'mainly clear'     => [1, '🌤️'],
            'partly cloudy'    => [2, '⛅'],
            'overcast'         => [3, '☁️'],
            'fog'              => [45, '🌫️'],
            'depositing rime'  => [48, '🌫️'],
            'light drizzle'    => [51, '🌦️'],
            'dense drizzle'    => [57, '🌦️'],
            'slight rain'      => [61, '🌧️'],
            'heavy rain'       => [65, '🌧️'],
            'slight snowfall'  => [71, '🌨️'],
            'heavy snowfall'   => [75, '🌨️'],
            'snow grains'      => [77, '🌨️'],
            'slight showers'   => [80, '🌧️'],
            'violent showers'  => [82, '🌧️'],
            'snow showers'     => [85, '🌨️'],
            'thunderstorm'     => [95, '⛈️'],
            'thunder + hail'   => [99, '⛈️'],
        ];
    }

    // --- bold kickoff row ---

    public function testKickoffRowIsBoldAndNoOtherRowIs(): void
    {
        $output = (string) $this->formatter->format(
            new WeatherSnapshot([
                $this->weatherHour('2026-04-15 17:00:00'),
                $this->weatherHour('2026-04-15 18:00:00'),
                $this->weatherHour('2026-04-15 19:00:00'),
            ]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        // MarkdownV2 bold wraps the line in *...*
        $this->assertStringContainsString('*🕘 18:00', $output);
        $this->assertStringNotContainsString('*🕘 17:00', $output);
        $this->assertStringNotContainsString('*🕘 19:00', $output);
    }

    // --- footer ---

    public function testFooterIsLinkWithCoordsInUrl(): void
    {
        $output = (string) $this->formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 18:00:00')]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        // MarkdownV2 link is [text](url)
        $this->assertStringContainsString('https://open-meteo.com/en/docs?latitude=41.3970&longitude=2.2110', $output);
    }

    public function testFooterAnchorContainsFetchedAtTime(): void
    {
        $output = (string) $this->formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 18:00:00')]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:34:56'),
        );

        $this->assertStringContainsString('Updated at 12:34', $output);
    }

    // --- wind direction compass ---

    #[DataProvider('compassBearings')]
    public function testWindDirectionRendersExpectedCompassPoint(int $degrees, string $expected): void
    {
        $output = (string) $this->formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 18:00:00', windDirectionDegrees: $degrees)]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertMatchesRegularExpression('/m\/s ' . preg_quote($expected, '/') . '\b/', $output);
    }

    /** @return array<string, array{int, string}> */
    public static function compassBearings(): array
    {
        return [
            'north at 0'      => [0, 'N'],
            'north at 360'    => [360, 'N'],
            'northeast at 45' => [45, 'NE'],
            'east at 90'      => [90, 'E'],
            'southeast at 135' => [135, 'SE'],
            'south at 180'    => [180, 'S'],
            'southwest at 225' => [225, 'SW'],
            'west at 270'     => [270, 'W'],
            'northwest at 315' => [315, 'NW'],
            'near north 10'   => [10, 'N'],
            'between n and ne 23'  => [23, 'NE'],
        ];
    }

    // --- wind speed & temperature rendering ---

    public function testWindSpeedRenderedAsIntegerMetersPerSecond(): void
    {
        $output = (string) $this->formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 18:00:00', windMetersPerSecond: 3.2)]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertStringContainsString('3 m/s', $output);
    }

    public function testTemperatureRenderedAsInteger(): void
    {
        $output = (string) $this->formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 18:00:00', temperatureC: 22.5)]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertStringContainsString('23°', $output);
    }

    // --- helpers ---

    private function weatherHour(
        string $at,
        float $temperatureC = 22.0,
        int $weatherCode = 0,
        float $windMetersPerSecond = 3.0,
        int $windDirectionDegrees = 0,
    ): WeatherHour {
        return new WeatherHour(
            hour: $this->hour($at),
            temperatureC: $temperatureC,
            weatherCode: $weatherCode,
            windMetersPerSecond: $windMetersPerSecond,
            windDirectionDegrees: $windDirectionDegrees,
        );
    }

    private function hour(string $at): DateTimeImmutable
    {
        return new DateTimeImmutable($at, new DateTimeZone('UTC'));
    }
}
