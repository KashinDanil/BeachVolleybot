<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Weather\CachedLocationResolver;
use BeachVolleybot\Weather\GameLocationResolver;
use BeachVolleybot\Weather\WeatherCacheManager;
use BeachVolleybot\Weather\WeatherFormatter;
use BeachVolleybot\Weather\WeatherWindowResolver;
use DateTimeZone;

final class WeatherAddOn implements GameAddOnInterface
{
    private const int WEATHER_SECTION_POSITION = 3;

    public function __construct(
        private readonly GameLocationResolver $locationResolver = new GameLocationResolver(
            new CachedLocationResolver(),
        ),
        private readonly WeatherCacheManager $weatherCache = new WeatherCacheManager(),
        private readonly WeatherWindowResolver $windowResolver = new WeatherWindowResolver(),
        private readonly WeatherFormatter $weatherFormatter = new WeatherFormatter(new MarkdownV2()),
    ) {
    }

    public function applyTo(Game $game): void
    {
        $section = $this->computeWeatherSection($game);
        if (null === $section) {
            return;
        }

        $previousSections = $game->telegramMessageBuilder->getEffective('getSections');
        $game->telegramMessageBuilder->override(
            'getSections',
            static function (GameInterface $game) use ($previousSections, $section): array {
                $sections = $previousSections($game);
                array_splice($sections, self::WEATHER_SECTION_POSITION, 0, [$section]);

                return $sections;
            }
        );
    }

    private function computeWeatherSection(Game $game): ?string
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

        return $this->weatherFormatter->format($row->snapshot, $row->coordinates, $window->kickoffHour, $row->fetchedAt);
    }
}