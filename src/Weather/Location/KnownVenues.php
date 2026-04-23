<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location;

use BeachVolleybot\Weather\Location\Models\LocationCoordinates;

final class KnownVenues
{
    /** @var list<VenueAlias>|null */
    private static ?array $matchIndex = null;

    public static function findInTitle(string $title): ?LocationCoordinates
    {
        $normalizedTitle = mb_strtolower($title);

        foreach (self::matchIndex() as $entry) {
            if (str_contains($normalizedTitle, $entry->alias)) {
                return $entry->coordinates;
            }
        }

        return null;
    }

    /**
     * Normalized aliases paired with their coordinates, sorted longest-first so
     * "Nova Mar Bella" wins over "Mar Bella" when both could match.
     *
     * @return list<VenueAlias>
     */
    private static function matchIndex(): array
    {
        return self::$matchIndex ??= self::buildMatchIndex(self::catalog());
    }

    /**
     * @param list<Venue> $venues
     *
     * @return list<VenueAlias>
     */
    private static function buildMatchIndex(array $venues): array
    {
        $entries = [];

        foreach ($venues as $venue) {
            foreach ($venue->aliases as $alias) {
                $entries[] = new VenueAlias(mb_strtolower($alias), $venue->coordinates);
            }
        }

        usort(
            $entries,
            static fn(VenueAlias $a, VenueAlias $b): int => mb_strlen($b->alias) - mb_strlen($a->alias),
        );

        return $entries;
    }

    /**
     * Barcelona city beaches (SW → NE along the coast) followed by nearby towns.
     * Coordinates are rounded to 3 decimals (~111 m), matching LocationCoordinates::rounded().
     *
     * @return list<Venue>
     */
    private static function catalog(): array
    {
        return [
            new Venue(new LocationCoordinates(41.378, 2.189), ['Sant Sebastià', 'Sant Sebastia', 'San Sebastián', 'San Sebastian', 'Сан Себастьян']),
            new Venue(new LocationCoordinates(41.381, 2.193), ['Barceloneta', 'Барселонета']),
            new Venue(new LocationCoordinates(41.383, 2.198), ['Somorrostro', 'Соморростро']),
            new Venue(new LocationCoordinates(41.388, 2.203), ['Nova Icària', 'Nova Icaria', 'Nueva Icaria', 'Нова Икария']),
            new Venue(new LocationCoordinates(41.394, 2.208), ['Platja del Bogatell', 'Playa de Bogatell', 'Bogatell', 'Богатель']),
            new Venue(new LocationCoordinates(41.400, 2.216), ['Platja de la Mar Bella', 'Mar Bella', 'Мар Белья']),
            new Venue(new LocationCoordinates(41.405, 2.224), ['Platja de la Nova Mar Bella', 'Nova Mar Bella', 'Нова Мар Белья']),
            new Venue(new LocationCoordinates(41.409, 2.229), ['Platja de Llevant', 'Llevant', 'Левант']),
            new Venue(new LocationCoordinates(41.267, 1.987), ['Castelldefels', 'Кастельдефельс']),
            new Venue(new LocationCoordinates(41.273, 2.013), ['Gavà Mar', 'Gava Mar', 'Гава']),
            new Venue(new LocationCoordinates(41.441, 2.244), ['Pont del Petroli', 'Badalona', 'Бадалона']),
            new Venue(new LocationCoordinates(41.480, 2.315), ['El Masnou', 'Masnou', 'Маснoу']),
            new Venue(new LocationCoordinates(41.232, 1.810), ['Platja de la Ribera', 'Sitges', 'Ситжес']),
        ];
    }
}