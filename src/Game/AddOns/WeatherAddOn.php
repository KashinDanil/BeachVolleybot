<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\MessageBuilders\GameMessageBuilder;
use BeachVolleybot\Weather\Forecast\GameWeatherLookup\GameWeatherLookup;
use BeachVolleybot\Weather\Forecast\WeatherFormatter;

final class WeatherAddOn implements GameAddOnInterface
{
    private const int WEATHER_SECTION_POSITION = 3;

    public function __construct(
        private readonly GameWeatherLookup $gameWeatherLookup = new GameWeatherLookup(),
    ) {
    }

    public function applyTo(Game $game): void
    {
        $section = $this->computeWeatherSection($game);
        if (null === $section) {
            return;
        }

        $this->installSectionOverride($game->telegramMessageBuilder, $section);
    }

    private function installSectionOverride(GameMessageBuilder $builder, string $section): void
    {
        $previousSections = $builder->getEffective('getSections');

        $builder->override(
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
        $lookup = $this->gameWeatherLookup->find($game);
        if (null === $lookup) {
            return null;
        }

        $weatherFormatter = new WeatherFormatter($game->telegramMessageBuilder->getFormatter());

        return $weatherFormatter->format(
            $lookup->row->snapshot,
            $lookup->row->coordinates,
            $lookup->kickoffHour,
            $lookup->row->fetchedAt,
        );
    }
}