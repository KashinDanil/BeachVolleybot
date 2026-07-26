<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location;

use BeachVolleybot\Weather\Location\Models\LocationCoordinates;

/**
 * The hand-maintained catalog of venues the bot can recognise in a game title.
 *
 * The matching itself lives in VenueDirectory; this class is the data plus a process-wide
 * directory over it, so every caller sees the same Venue instances.
 */
final class KnownVenues
{
    private static ?VenueDirectory $directory = null;

    /**
     * @return list<Venue>
     */
    public static function all(): array
    {
        return self::directory()->all();
    }

    public static function findInTitle(string $title): ?Venue
    {
        return self::directory()->findInTitle($title);
    }

    public static function findByName(string $name): ?Venue
    {
        return self::directory()->findByName($name);
    }

    private static function directory(): VenueDirectory
    {
        return self::$directory ??= new VenueDirectory(self::catalog());
    }

    /**
     * Barcelona-area beaches, ordered SW → NE along the coast (latitude ascending).
     * Coordinates are rounded to 3 decimals (~111 m), matching LocationCoordinates::rounded().
     *
     * @return list<Venue>
     */
    private static function catalog(): array
    {
        return [
            self::venue('Bogatell', 41.394, 2.208, 'Platja del Bogatell', 'Playa de Bogatell', 'Богатель'),
            self::venue('Fòrum', 41.415, 2.205, 'Platja del Fòrum', 'Forum', 'Форум'),
            self::venue('Nova Icària', 41.388, 2.203, 'Nova Icaria', 'Nueva Icaria', 'Нова Икария'),
            self::venue('Sant Sebastià', 41.378, 2.189, 'Sant Sebastia', 'San Sebastián', 'San Sebastian', 'Сан Себастьян'),
            self::venue('Barceloneta', 41.381, 2.193, 'Барселонета'),
            self::venue('Somorrostro', 41.383, 2.198, 'Соморростро'),
            self::venue('Mar Bella', 41.400, 2.216, 'Platja de la Mar Bella', 'Мар Белья'),
            self::venue('Nova Mar Bella', 41.405, 2.224, 'Platja de la Nova Mar Bella', 'Нова Мар Белья'),
            self::venue('Besòs', 41.422, 2.232, 'Platja de Sant Adrià de Besòs', 'Sant Andria', 'Besos', 'Бесос'),
            self::venue('Sitges', 41.232, 1.810, 'Platja de la Ribera', 'Ситжес'),
            self::venue('Castelldefels', 41.267, 1.987, 'Кастельдефельс'),
            self::venue('Gavà Mar', 41.273, 2.013, 'Gava Mar', 'Гава'),
            self::venue('Llevant', 41.409, 2.229, 'Platja de Llevant', 'Левант'),
            self::venue('Mora', 41.432, 2.238, 'Platja de la Mora'),
            self::venue('Coco', 41.439, 2.246, 'Platja del Coco'),
            self::venue('Badalona', 41.441, 2.244, 'Pont del Petroli', 'Бадалона'),
            self::venue('Masnou', 41.480, 2.315, 'El Masnou', 'Масноу'),
        ];
    }

    private static function venue(string $name, float $latitude, float $longitude, string ...$aliases): Venue
    {
        return new Venue($name, new LocationCoordinates($latitude, $longitude), $aliases);
    }
}
