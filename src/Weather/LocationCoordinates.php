<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

readonly class LocationCoordinates
{
    private const int ROUND_PRECISION = 3;

    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
    }

    public static function tryParse(?string $raw): ?self
    {
        if (null === $raw) {
            return null;
        }

        $parts = explode(',', $raw);

        if (2 !== count($parts)) {
            return null;
        }

        $latitude = self::tryParseCoordinate(trim($parts[0]));
        $longitude = self::tryParseCoordinate(trim($parts[1]));

        if (null === $latitude || null === $longitude) {
            return null;
        }

        if ($latitude < -90.0 || $latitude > 90.0) {
            return null;
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            return null;
        }

        return new self($latitude, $longitude);
    }

    public function rounded(): self
    {
        return new self(
            round($this->latitude, self::ROUND_PRECISION),
            round($this->longitude, self::ROUND_PRECISION),
        );
    }

    private static function tryParseCoordinate(string $value): ?float
    {
        if ('' === $value) {
            return null;
        }

        if (1 !== preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return null;
        }

        return (float) $value;
    }
}
