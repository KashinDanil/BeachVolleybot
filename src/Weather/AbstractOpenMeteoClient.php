<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use RuntimeException;

abstract readonly class AbstractOpenMeteoClient
{
    private const int TIMEOUT_SECONDS = 5;

    /**
     * @param array<string, scalar> $queryParams
     *
     * @return array<string, mixed>
     */
    protected function get(string $baseUrl, array $queryParams): array
    {
        return $this->fetchJson($baseUrl . '?' . http_build_query($queryParams));
    }

    /** @return array<string, mixed> */
    private function fetchJson(string $url): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($curl);

        if (false === $response) {
            throw new RuntimeException('Open-Meteo request failed: ' . curl_error($curl));
        }

        return json_decode((string)$response, associative: true, flags: JSON_THROW_ON_ERROR);
    }
}
