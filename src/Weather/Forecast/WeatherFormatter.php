<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast;

use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Weather\Forecast\Models\WeatherHour;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeImmutable;

final readonly class WeatherFormatter
{
    private const string DEFAULT_WEATHER_EMOJI = '🌤️';
    private const string HOUR_PREFIX_EMOJI     = '🕘';
    private const string WIND_EMOJI            = '💨';

    private const string OPEN_METEO_URL_TEMPLATE = 'https://open-meteo.com/en/docs?latitude=%.4f&longitude=%.4f';

    private const int DEGREES_PER_COMPASS_POINT = 45;

    /** @var list<string> */
    private const array COMPASS_POINTS = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];

    public function __construct(
        private MessageFormatterInterface $messageFormatter = new MarkdownV2(),
    ) {
    }

    public function format(
        WeatherSnapshot $snapshot,
        LocationCoordinates $coordinates,
        DateTimeImmutable $kickoffHour,
        DateTimeImmutable $fetchedAt,
    ): ?string {
        if (empty($snapshot->hours)) {
            return null;
        }

        $heading = $this->buildHeading($snapshot, $kickoffHour);
        $rows = $this->buildRows($snapshot, $kickoffHour);
        $footer = $this->buildFooter($fetchedAt, $coordinates);

        return implode($this->messageFormatter->newLine(), [$heading, ...$rows, $footer]);
    }

    private function buildHeading(WeatherSnapshot $snapshot, DateTimeImmutable $kickoffHour): string
    {
        $kickoffCode = $snapshot->forHour($kickoffHour)?->weatherCode;
        $emoji = $this->emojiForWeatherCode($kickoffCode);

        return "$emoji Weather";
    }

    /** @return list<string> */
    private function buildRows(WeatherSnapshot $snapshot, DateTimeImmutable $kickoffHour): array
    {
        return array_map(
            fn(WeatherHour $hour): string => $this->formatRow($hour, $kickoffHour),
            $snapshot->hours,
        );
    }

    private function formatRow(WeatherHour $hour, DateTimeImmutable $kickoffHour): string
    {
        $line = implode(' ', [
            self::HOUR_PREFIX_EMOJI,
            $hour->hour->format('H:i'),
            $this->emojiForWeatherCode($hour->weatherCode),
            $this->formatTemperature($hour->temperatureC),
            $this->formatWind($hour),
        ]);

        return $this->isKickoffHour($hour, $kickoffHour)
            ? $this->messageFormatter->bold($line)
            : $line;
    }

    private function formatTemperature(float $celsius): string
    {
        return (int)round($celsius) . '°';
    }

    private function formatWind(WeatherHour $hour): string
    {
        return sprintf(
            '%s %d m/s %s',
            self::WIND_EMOJI,
            (int)round($hour->windMetersPerSecond),
            $this->compassDirection($hour->windDirectionDegrees),
        );
    }

    private function isKickoffHour(WeatherHour $hour, DateTimeImmutable $kickoffHour): bool
    {
        // Wall-clock match (each side's own zone), not absolute timestamp: the
        // snapshot's hours carry Open-Meteo's local zone while $kickoffHour
        // typically arrives in UTC (from the game's created_at). Comparing
        // `Y-m-d H` makes "kickoff is 18:00" pick the row labelled 18:00,
        // regardless of zone offsets — same semantic as WeatherSnapshot::forHour.
        return $hour->hour->format('Y-m-d H') === $kickoffHour->format('Y-m-d H');
    }

    private function buildFooter(DateTimeImmutable $fetchedAt, LocationCoordinates $coordinates): string
    {
        $anchor = 'Updated at ' . $fetchedAt->format('H:i');
        $url = sprintf(self::OPEN_METEO_URL_TEMPLATE, $coordinates->latitude, $coordinates->longitude);

        return $this->messageFormatter->link($anchor, $url);
    }

    /**
     * Weather codes follow WMO 4677 as returned by Open-Meteo.
     *
     * @see https://open-meteo.com/en/docs
     * @see https://codes.wmo.int/bufr4/codeflag/_0-20-003
     */
    private function emojiForWeatherCode(?int $weatherCode): string
    {
        return match (true) {
            // Kickoff hour missing from the snapshot — defensive fallback.
            null === $weatherCode => self::DEFAULT_WEATHER_EMOJI,

            // Clear & cloud cover (WMO 00-03)
            0 === $weatherCode => '☀️',
            1 === $weatherCode => '🌤️',
            2 === $weatherCode => '⛅',
            3 === $weatherCode => '☁️',

            // Fog / ice fog (WMO 40-49)
            $weatherCode >= 40 && $weatherCode <= 49 => '🌫️',

            // Drizzle (WMO 50-59)
            $weatherCode >= 50 && $weatherCode <= 59 => '🌦️',

            // Rain + rain showers + hail showers (WMO 60-69, 80-84, 87-89)
            ($weatherCode >= 60 && $weatherCode <= 69)
            || ($weatherCode >= 80 && $weatherCode <= 84)
            || ($weatherCode >= 87 && $weatherCode <= 89) => '🌧️',

            // Snowfall + snow showers (WMO 70-79, 85-86)
            ($weatherCode >= 70 && $weatherCode <= 79)
            || ($weatherCode >= 85 && $weatherCode <= 86) => '🌨️',

            // Thunderstorm (WMO 90-99)
            $weatherCode >= 90 && $weatherCode <= 99 => '⛈️',

            default => self::DEFAULT_WEATHER_EMOJI,
        };
    }

    private function compassDirection(int $degrees): string
    {
        $index = (int)round($degrees / self::DEGREES_PER_COMPASS_POINT) % count(self::COMPASS_POINTS);

        return self::COMPASS_POINTS[$index];
    }
}