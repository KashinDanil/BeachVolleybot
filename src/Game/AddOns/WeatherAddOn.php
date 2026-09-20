<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\Game\GameMessageBuilder;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Weather\Forecast\GameWeatherLookup\GameWeatherLookup;
use BeachVolleybot\Weather\Forecast\GameWeatherLookup\GameWeatherLookupResult;
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
        $lookup = $this->gameWeatherLookup->findForGame($game);

        if (null === $lookup) {
            return;
        }

        $this->installSectionOverride($game->telegramMessageBuilder, $lookup);
    }

    private function installSectionOverride(GameMessageBuilder $builder, GameWeatherLookupResult $lookup): void
    {
        $previousSections = $builder->getEffective('getSections');
        $formatter = $builder->getFormatter();

        $builder->override(
            'getSections',
            static function (GameInterface $game, Translator $translator) use ($previousSections, $lookup, $formatter): array {
                $sections = $previousSections($game, $translator);
                $section = self::computeWeatherSection($lookup, $formatter, $translator);

                if (null !== $section) {
                    array_splice($sections, self::WEATHER_SECTION_POSITION, 0, [$section]);
                }

                return $sections;
            }
        );
    }

    private static function computeWeatherSection(
        GameWeatherLookupResult $lookup,
        MessageFormatterInterface $formatter,
        Translator $translator,
    ): ?string {
        return new WeatherFormatter($translator, $formatter)->format(
            $lookup->row->snapshot,
            $lookup->row->coordinates,
            $lookup->kickoffHour,
            $lookup->row->fetchedAt,
        );
    }
}