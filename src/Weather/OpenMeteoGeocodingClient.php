<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

final readonly class OpenMeteoGeocodingClient extends AbstractOpenMeteoClient implements GeocodingClientInterface
{
    public const string BASE_URL = 'https://geocoding-api.open-meteo.com/v1/search';

    private const int RESULT_COUNT = 1;

    public function resolve(string $query): ?LocationCoordinates
    {
        $response = $this->get(self::BASE_URL, [
            'name' => $query,
            'count' => self::RESULT_COUNT,
            'language' => 'en',
            'format' => 'json',
        ]);

        $first = $response['results'][0] ?? null;

        if (!is_array($first) || !isset($first['latitude'], $first['longitude'])) {
            return null;
        }

        return new LocationCoordinates(
            latitude: (float)$first['latitude'],
            longitude: (float)$first['longitude'],
        );
    }
}
