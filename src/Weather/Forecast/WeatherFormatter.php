<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Weather\Forecast\Models\WeatherHour;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeImmutable;

final readonly class WeatherFormatter
{
    private const string DEFAULT_WEATHER_EMOJI = '🌤️';
    private const string WIND_EMOJI          = '💨';
    private const string ROW_GROUP_SEPARATOR = '   ';

    private const string WEATHER_HEADING        = 'Weather';
    private const string UPDATED_AT_FORMAT      = 'Updated at %s';
    private const string METERS_PER_SECOND_UNIT = 'm/s';

    private const string OPEN_METEO_URL_TEMPLATE = 'https://open-meteo.com/en/docs?latitude=%.4f&longitude=%.4f';

    private const int DEGREES_PER_COMPASS_POINT = 45;

    /** @var list<string> */
    private const array COMPASS_POINTS = ['↑', '↗', '→', '↘', '↓', '↙', '←', '↖'];

    public function __construct(
        private Translator $translator,
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

        // Cached hours and fetchedAt are UTC; the kickoff carries the venue's clock to read them by.
        $heading = $this->buildHeading();
        $rows = $this->buildRows($snapshot, $kickoffHour);
        $footer = $this->buildFooter($fetchedAt->setTimezone($kickoffHour->getTimezone()), $coordinates);
        $section = implode($this->messageFormatter->newLine(), [$heading, ...$rows, $footer]);

        return $this->messageFormatter->blockquote($section) . $this->messageFormatter->newLine();
    }

    private function buildHeading(): string
    {
        return $this->messageFormatter->bold($this->translator->translate(self::WEATHER_HEADING));
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
        $time = $hour->hour->setTimezone($kickoffHour->getTimezone())->format('H:i');
        $sky = $this->emojiForWeatherCode($hour->weatherCode) . ' ' . $this->formatTemperature($hour->temperatureC);
        $wind = $this->formatWind($hour);
        $line = implode(self::ROW_GROUP_SEPARATOR, [$time, $sky, $wind]);

        return $this->isKickoffHour($hour, $kickoffHour)
            ? $this->messageFormatter->bold($line)
            : $line;
    }

    private function formatTemperature(float $celsius): string
    {
        return (int)round($celsius) . '°';
    }

    /**
     * Escaped here rather than left to the row's occasional bold() wrap: a non-kickoff row
     * reaches blockquote() with no escaping at all, and blockquote() itself never escapes.
     */
    private function formatWind(WeatherHour $hour): string
    {
        return sprintf(
            '%s %s %d %s',
            self::WIND_EMOJI,
            $this->compassDirection($hour->windDirectionDegrees),
            (int)round($hour->windMetersPerSecond),
            $this->messageFormatter->escape($this->translator->translate(self::METERS_PER_SECOND_UNIT)),
        );
    }

    private function isKickoffHour(WeatherHour $hour, DateTimeImmutable $kickoffHour): bool
    {
        return $hour->hour->getTimestamp() === $kickoffHour->getTimestamp();
    }

    private function buildFooter(DateTimeImmutable $fetchedAt, LocationCoordinates $coordinates): string
    {
        $anchor = sprintf($this->translator->translate(self::UPDATED_AT_FORMAT), $fetchedAt->format('H:i'));
        $url = sprintf(self::OPEN_METEO_URL_TEMPLATE, $coordinates->latitude, $coordinates->longitude);

        return $this->messageFormatter->link($anchor, $url);
    }

    /**
     * Weather codes follow WMO 4677 as returned by Open-Meteo.
     *
     * @see https://open-meteo.com/en/docs
     * @see https://codes.wmo.int/bufr4/codeflag/_0-20-003
     */
    private function emojiForWeatherCode(int $weatherCode): string
    {
        return match (true) {
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