<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast\GameWeatherLookup;

use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheManager;
use BeachVolleybot\Weather\Forecast\WeatherWindowResolver;
use BeachVolleybot\Weather\Location\GameLocationResolver;
use DateTimeImmutable;
use DateTimeZone;

final readonly class GameWeatherLookup
{
    public function __construct(
        private GameLocationResolver $locationResolver = new GameLocationResolver(),
        private WeatherCacheManager $weatherCache = new WeatherCacheManager(),
        private WeatherWindowResolver $windowResolver = new WeatherWindowResolver(),
    ) {
    }

    public function findForGameRecord(GameRecord $game): ?GameWeatherLookupResult
    {
        return $this->find(
            $game->kickoffAt,
            $game->location,
            $game->venueName,
            $game->title
        );
    }

    /** The card is also rendered for the /new_game preview, which has no games row to read. */
    public function findForGame(GameInterface $game): ?GameWeatherLookupResult
    {
        return $this->find(
            $game->getKickoffAt(),
            $game->getLocation(),
            $game->getVenueName(),
            $game->getTitle(),
        );
    }

    private function find(
        DateTimeImmutable $kickoffAt,
        ?string $location,
        ?string $venueName,
        string $title,
    ): ?GameWeatherLookupResult {
        // The display path is not horizon-gated: once a forecast is in the DB, we
        // keep surfacing it, even past kickoff. Fetching is still horizon-gated
        // via WeatherWindowResolver in WeatherQueueProcessor.
        $window = $this->windowResolver->windowFor($kickoffAt);

        $coordinates = $this->locationResolver->resolve($location, $venueName, $title)->rounded();
        $kickoffUtc = $window->kickoffHour->setTimezone(new DateTimeZone('UTC'));
        $row = $this->weatherCache->find($coordinates, $kickoffUtc);

        if (null === $row) {
            return null;
        }

        return new GameWeatherLookupResult($row, $window->kickoffHour);
    }
}
