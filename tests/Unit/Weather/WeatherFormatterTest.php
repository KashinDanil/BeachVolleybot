<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Weather\Forecast\Models\WeatherHour;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Forecast\WeatherFormatter;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DanilKashin\Localization\Language;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeatherFormatterTest extends TestCase
{
    private WeatherFormatter $formatter;

    private Translator $translator;

    protected function setUp(): void
    {
        $this->translator = new Translator();
        $this->formatter = new WeatherFormatter($this->translator);
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

    // --- blockquote wrapping ---

    public function testEveryLineIsBlockquoted(): void
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

        foreach (array_filter(explode("\n", $output)) as $line) {
            $this->assertStringStartsWith('>', $line);
        }
    }

    // --- heading ---

    public function testHeadingIsBoldWeatherLabel(): void
    {
        $output = $this->formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 18:00:00', weatherCode: 0)]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertStringStartsWith('>*Weather*', (string) $output);
    }

    public function testHeadingIsTranslatedForANonDefaultLocale(): void
    {
        $translator = new Translator(Language::RU, tempnam(sys_get_temp_dir(), 'bvb_missing_'));
        $formatter = new WeatherFormatter($translator);

        $output = $formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 18:00:00', weatherCode: 0)]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertStringStartsWith('>*Погода*', (string) $output);
    }

    // --- row layout ---

    public function testRowLaysOutTimeSkyAndWindGroupsInOrder(): void
    {
        $output = (string) $this->formatter->format(
            new WeatherSnapshot([$this->weatherHour(
                '2026-04-15 17:00:00',
                temperatureC: 22.0,
                weatherCode: 0,
                windMetersPerSecond: 3.0,
                windDirectionDegrees: 0,
            )]),
            new LocationCoordinates(41.397, 2.211),
            // Kickoff at 18:00 so the 17:00 row isn't bold-wrapped — assertion
            // reads the raw row contents.
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertStringContainsString('17:00   ☀️ 22°   💨 ↑ 3 m/s', $output);
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
        $this->assertStringContainsString('*18:00', $output);
        $this->assertStringNotContainsString('*17:00', $output);
        $this->assertStringNotContainsString('*19:00', $output);
    }

    public function testKickoffRowBoldMatchesWallClockEvenWhenZonesDiffer(): void
    {
        // Snapshot hours in Madrid local time (what Open-Meteo returns with timezone=auto).
        $snapshotZone = new DateTimeZone('Europe/Madrid');
        $kickoffZone = new DateTimeZone('UTC');

        $output = (string) $this->formatter->format(
            new WeatherSnapshot([
                $this->weatherHour('2026-04-15 17:00:00', zone: $snapshotZone),
                $this->weatherHour('2026-04-15 18:00:00', zone: $snapshotZone),
                $this->weatherHour('2026-04-15 19:00:00', zone: $snapshotZone),
                $this->weatherHour('2026-04-15 20:00:00', zone: $snapshotZone),
            ]),
            new LocationCoordinates(41.397, 2.211),
            // Kickoff in UTC — same wall-clock 18:00 as the intended Madrid hour,
            // but its epoch matches Madrid 20:00 (UTC+2). Bold must still land on 18:00.
            new DateTimeImmutable('2026-04-15 18:00:00', $kickoffZone),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertStringContainsString('*18:00', $output);
        $this->assertStringNotContainsString('*20:00', $output);
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

    public function testFooterAnchorRendersFetchedAtInKickoffLocationZone(): void
    {
        // Cached hours and fetchedAt are UTC; the kickoff hour carries the venue's clock.
        $output = (string) $this->formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 16:00:00')]),
            new LocationCoordinates(41.397, 2.211),
            new DateTimeImmutable('2026-04-15 18:00:00', new DateTimeZone('Europe/Madrid')),
            // 12:34 UTC = 14:34 in Madrid (UTC+2 in April).
            $this->hour('2026-04-15 12:34:56'),
        );

        $this->assertStringContainsString('Updated at 14:34', $output);
        $this->assertStringNotContainsString('Updated at 12:34', $output);
    }

    public function testFooterIsTranslatedForANonDefaultLocale(): void
    {
        $translator = new Translator(Language::RU, tempnam(sys_get_temp_dir(), 'bvb_missing_'));
        $formatter = new WeatherFormatter($translator);

        $output = (string) $formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 18:00:00')]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:34:56'),
        );

        $this->assertStringContainsString('Обновлено в 12:34', $output);
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

        $this->assertStringContainsString('💨 ' . $expected, $output);
    }

    /** @return array<string, array{int, string}> */
    public static function compassBearings(): array
    {
        return [
            'north at 0'       => [0, '↑'],
            'north at 360'     => [360, '↑'],
            'northeast at 45'  => [45, '↗'],
            'east at 90'       => [90, '→'],
            'southeast at 135' => [135, '↘'],
            'south at 180'     => [180, '↓'],
            'southwest at 225' => [225, '↙'],
            'west at 270'      => [270, '←'],
            'northwest at 315' => [315, '↖'],
            'near north 10'    => [10, '↑'],
            'between n and ne 23'  => [23, '↗'],
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

    public function testWindUnitIsTranslatedForANonDefaultLocale(): void
    {
        $translator = new Translator(Language::RU, tempnam(sys_get_temp_dir(), 'bvb_missing_'));
        $formatter = new WeatherFormatter($translator);

        $output = (string) $formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 18:00:00', windMetersPerSecond: 3.2)]),
            new LocationCoordinates(41.397, 2.211),
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertStringContainsString('3 м/с', $output);
    }

    /**
     * A non-kickoff row is never wrapped in bold() (which would otherwise escape it), and
     * blockquote() never escapes either — so the wind unit must escape itself. Uses a stub
     * translator so this doesn't depend on today's locale files staying free of special chars.
     */
    public function testWindUnitIsEscapedOnANonKickoffRowEvenWithSpecialCharacters(): void
    {
        $translator = $this->createStub(Translator::class);
        $translator->method('translate')->willReturnCallback(
            static fn(string $text): string => 'm/s' === $text ? 'm.s (bad)' : $text,
        );
        $formatter = new WeatherFormatter($translator);

        $output = (string) $formatter->format(
            new WeatherSnapshot([$this->weatherHour('2026-04-15 17:00:00')]),
            new LocationCoordinates(41.397, 2.211),
            // Kickoff is 18:00, so the 17:00 row above is never bold()-wrapped.
            $this->hour('2026-04-15 18:00:00'),
            $this->hour('2026-04-15 12:00:00'),
        );

        $this->assertStringContainsString('m\\.s \\(bad\\)', $output);
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
        ?DateTimeZone $zone = null,
    ): WeatherHour {
        return new WeatherHour(
            hour: new DateTimeImmutable($at, $zone ?? new DateTimeZone('UTC')),
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
