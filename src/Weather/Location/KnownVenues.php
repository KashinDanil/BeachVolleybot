<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location;

use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeZone;

/**
 * The hand-maintained catalog of venues the bot can recognise in a game title.
 *
 * The matching itself lives in VenueDirectory; this class is the data plus a process-wide
 * directory over it, so every caller sees the same Venue instances.
 */
final class KnownVenues
{
    private static ?VenueDirectory $directory = null;

    /** The home beach, standing in for a game whose venue we do not recognise. */
    public static function defaultVenue(): Venue
    {
        return self::all()[0];
    }

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

    /** For the write path, where the title is all there is — venue_name is derived from it. */
    public static function findInTitleOrDefault(string $title): Venue
    {
        return self::findInTitle($title) ?? self::defaultVenue();
    }

    public static function findByNameOrDefault(?string $venueName): Venue
    {
        return self::findByName($venueName ?? '') ?? self::defaultVenue();
    }

    private static function directory(): VenueDirectory
    {
        return self::$directory ??= new VenueDirectory(self::catalog());
    }

    /**
     * Barcelona-area beaches. The first row is the default venue, so keep the home beach there.
     * Coordinates are rounded to 3 decimals (~111 m), matching LocationCoordinates::rounded().
     *
     * @return list<Venue>
     */
    private static function catalog(): array
    {
        return [
            self::venue('Bogatell', 41.394, 2.208, ['Platja del Bogatell', 'Playa de Bogatell', 'Богатель'], 'Europe/Madrid'),
            self::venue('Fòrum', 41.415, 2.205, ['Platja del Fòrum', 'Forum', 'Форум'], 'Europe/Madrid'),
            self::venue('Nova Icària', 41.388, 2.203, ['Nova Icaria', 'Nueva Icaria', 'Нова Икария'], 'Europe/Madrid'),
            self::venue('Sant Sebastià', 41.378, 2.189, ['Sant Sebastia', 'San Sebastián', 'San Sebastian', 'Сан Себастьян'], 'Europe/Madrid'),
            self::venue('Barceloneta', 41.381, 2.193, ['Барселонета'], 'Europe/Madrid'),
            self::venue('Somorrostro', 41.383, 2.198, ['Соморростро'], 'Europe/Madrid'),
            self::venue('Mar Bella', 41.400, 2.216, ['Platja de la Mar Bella', 'Мар Белья'], 'Europe/Madrid'),
            self::venue('Nova Mar Bella', 41.405, 2.224, ['Platja de la Nova Mar Bella', 'Нова Мар Белья'], 'Europe/Madrid'),
            self::venue('Besòs', 41.422, 2.232, ['Platja de Sant Adrià de Besòs', 'Sant Andria', 'Besos', 'Бесос'], 'Europe/Madrid'),
            self::venue('Sitges', 41.232, 1.810, ['Platja de la Ribera', 'Ситжес'], 'Europe/Madrid'),
            self::venue('Castelldefels', 41.267, 1.987, ['Кастельдефельс'], 'Europe/Madrid'),
            self::venue('Gavà Mar', 41.273, 2.013, ['Gava Mar', 'Гава'], 'Europe/Madrid'),
            self::venue('Llevant', 41.409, 2.229, ['Platja de Llevant', 'Левант'], 'Europe/Madrid'),
            self::venue('Mora', 41.432, 2.238, ['Platja de la Mora'], 'Europe/Madrid'),
            self::venue('Coco', 41.439, 2.246, ['Platja del Coco'], 'Europe/Madrid'),
            self::venue('Badalona', 41.441, 2.244, ['Pont del Petroli', 'Бадалона'], 'Europe/Madrid'),
            self::venue('Masnou', 41.480, 2.315, ['El Masnou', 'Масноу'], 'Europe/Madrid'),
        ];
    }

    /** @param list<string> $aliases */
    private static function venue(
        string $name,
        float $latitude,
        float $longitude,
        array $aliases,
        string $timezone,
    ): Venue {
        return new Venue($name, new LocationCoordinates($latitude, $longitude), $aliases, new DateTimeZone($timezone));
    }
}
