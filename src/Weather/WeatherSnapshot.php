<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use DateTimeImmutable;
use JsonSerializable;

final readonly class WeatherSnapshot implements JsonSerializable
{
    /** @param list<WeatherHour> $hours */
    public function __construct(
        public array $hours,
    ) {
    }

    public function forHour(DateTimeImmutable $target): ?WeatherHour
    {
        $targetKey = $target->format('Y-m-d H');

        return array_find($this->hours, static fn($hour) => $hour->hour->format('Y-m-d H') === $targetKey);
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return array_map(static fn(WeatherHour $hour): array => $hour->jsonSerialize(), $this->hours);
    }

    /** @param list<array<string, mixed>> $hours */
    public static function fromArray(array $hours): self
    {
        $weatherHours = array_map(
        /** @param array<string, mixed> $hourData */
            static fn(array $hourData): WeatherHour => WeatherHour::fromArray($hourData),
            $hours,
        );

        return new self(array_values($weatherHours));
    }
}
