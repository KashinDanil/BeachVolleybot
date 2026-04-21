<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use DateTimeImmutable;

final class GameDateTimeResolver
{
    public static function resolve(string $title, DateTimeImmutable $creationDate): ?DateTimeImmutable
    {
        $time = TimeExtractor::extract($title);

        if (null === $time) {
            return null;
        }

        $gameDate = GameDateResolver::resolve($title, $creationDate) ?? $creationDate;
        [$hour, $minute] = explode(':', $time);

        return $gameDate->setTime((int) $hour, (int) $minute);
    }
}