<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast\Cache;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Weather\Forecast\Client\OpenMeteoWeatherClient;
use BeachVolleybot\Weather\Forecast\Client\WeatherApiClientInterface;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Forecast\Models\WeatherWindow;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final readonly class WeatherCacheUpdater
{
    public const int CACHE_TTL_SECONDS = 300;

    public function __construct(
        private WeatherApiClientInterface $weatherClient = new OpenMeteoWeatherClient(),
        private WeatherCacheManager $cache = new WeatherCacheManager(),
    ) {
    }

    public function update(LocationCoordinates $coordinates, WeatherWindow $window): bool
    {
        $kickoffUtc = $window->kickoffHour->setTimezone(new DateTimeZone('UTC'));

        if (!$this->needsUpdate($coordinates, $kickoffUtc)) {
            return false;
        }

        $snapshot = $this->tryFetchSnapshot($coordinates, $window);

        if (null === $snapshot) {
            return false;
        }

        $this->cache->save($coordinates, $kickoffUtc, $snapshot);

        return true;
    }

    private function isFresh(?WeatherCacheRow $row): bool
    {
        if (null === $row) {
            return false;
        }

        return time() - $row->fetchedAt->getTimestamp() < self::CACHE_TTL_SECONDS;
    }

    private function needsUpdate(LocationCoordinates $coordinates, DateTimeImmutable $kickoffUtc): bool
    {
        return !$this->isFresh($this->cache->find($coordinates, $kickoffUtc));
    }

    private function tryFetchSnapshot(LocationCoordinates $coordinates, WeatherWindow $window): ?WeatherSnapshot
    {
        try {
            return $this->weatherClient->fetch(
                $coordinates,
                $window->hours[0],
                $window->hours[array_key_last($window->hours)],
            );
        } catch (Throwable $e) {
            Logger::logApp('Weather fetch failed: ' . $e->getMessage());

            return null;
        }
    }
}
