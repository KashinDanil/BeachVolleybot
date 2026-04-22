<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast\GameWeatherLookup;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheManager;
use BeachVolleybot\Weather\Forecast\WeatherWindowResolver;
use BeachVolleybot\Weather\Location\GameLocationResolver;
use BeachVolleybot\Weather\Location\Resolvers\CachedLocationResolver;
use DateTimeZone;

final readonly class GameWeatherLookup
{
    public function __construct(
        private GameLocationResolver $locationResolver = new GameLocationResolver(
            new CachedLocationResolver(),
        ),
        private WeatherCacheManager $weatherCache = new WeatherCacheManager(),
        private WeatherWindowResolver $windowResolver = new WeatherWindowResolver(),
    ) {
    }

    public function find(GameInterface $game): ?GameWeatherLookupResult
    {
        $window = $this->windowResolver->windowForGame($game);
        if (empty($window->hours)) {
            return null;
        }

        $coordinates = $this->locationResolver->resolve($game)->rounded();
        $kickoffUtc = $window->kickoffHour->setTimezone(new DateTimeZone('UTC'));
        $row = $this->weatherCache->find($coordinates, $kickoffUtc);

        if (null === $row) {
            return null;
        }

        return new GameWeatherLookupResult($row, $window->kickoffHour);
    }
}