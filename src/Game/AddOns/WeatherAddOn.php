<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Processors\UpdateProcessors\CallbackAction;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use BeachVolleybot\Telegram\MessageBuilders\GameMessageBuilder;
use BeachVolleybot\Weather\Forecast\GameWeatherLookup\GameWeatherLookup;
use BeachVolleybot\Weather\Forecast\WeatherFormatter;

final class WeatherAddOn implements GameAddOnInterface
{
    private const int WEATHER_SECTION_POSITION = 3;

    private const string REFRESH_BUTTON_LABEL = '🔄 Weather';

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
        $this->installKeyboardOverride($game->telegramMessageBuilder);
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

    private function installKeyboardOverride(GameMessageBuilder $builder): void
    {
        $previousKeyboard = $builder->getEffective('buildKeyboard');

        $builder->override(
            'buildKeyboard',
            static function (GameInterface $game) use ($previousKeyboard, $builder): array {
                $rows = $previousKeyboard($game);

                if (GameDateTimeResolver::isKickoffPast($game->getTitle(), $game->getCreatedAt())) {
                    return $rows;
                }

                $rows[] = [
                    $builder->buildActionButton(
                        self::REFRESH_BUTTON_LABEL,
                        CallbackData::create(CallbackAction::RefreshWeather),
                    ),
                ];

                return $rows;
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