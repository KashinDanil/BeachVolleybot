<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Queue;

use BeachVolleybot\Database\Timestamp;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Weather\Forecast\WeatherWindowResolver;
use BeachVolleybot\Weather\Location\GameLocationResolver;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeImmutable;
use JsonSerializable;
use Throwable;

final readonly class WeatherQueuePayload implements JsonSerializable
{
    private function __construct(
        public LocationCoordinates $coordinates,
        public DateTimeImmutable $forecastTs,
    ) {
    }

    public static function createRounded(LocationCoordinates $coordinates, DateTimeImmutable $dateTime): self
    {
        return new self(
            $coordinates->rounded(),
            new WeatherWindowResolver()->roundToNearestHour($dateTime),
        );
    }

    public static function forGameRecord(GameRecord $game): self
    {
        return self::forParts($game->location, $game->venueName, $game->kickoffAt);
    }

    public static function forGame(GameInterface $game): self
    {
        return self::forParts($game->getLocation(), $game->getVenueName(), $game->getKickoffAt());
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        if (!isset($data['latitude'], $data['longitude'], $data['forecast_ts'])) {
            return null;
        }

        if (!is_numeric($data['latitude']) || !is_numeric($data['longitude'])) {
            return null;
        }

        if (!is_string($data['forecast_ts']) || '' === $data['forecast_ts']) {
            return null;
        }

        $coordinates = LocationCoordinates::tryCreate((float)$data['latitude'], (float)$data['longitude']);

        if (null === $coordinates) {
            return null;
        }

        try {
            return self::createRounded($coordinates, Timestamp::parse($data['forecast_ts']));
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{latitude: float, longitude: float, forecast_ts: string} */
    public function jsonSerialize(): array
    {
        return [
            'latitude' => $this->coordinates->latitude,
            'longitude' => $this->coordinates->longitude,
            'forecast_ts' => Timestamp::format($this->forecastTs),
        ];
    }

    public function id(): string
    {
        return sprintf(
            '%s_%s_%d',
            $this->coordinates->latitude,
            $this->coordinates->longitude,
            $this->forecastTs->getTimestamp(),
        );
    }

    private static function forParts(?string $location, ?string $venueName, DateTimeImmutable $kickoffAt): self
    {
        return self::createRounded(new GameLocationResolver()->resolve($location, $venueName), $kickoffAt);
    }
}
