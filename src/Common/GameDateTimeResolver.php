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

    public static function isKickoffPast(
        string $title,
        DateTimeImmutable $creationDate,
        ?DateTimeImmutable $now = null,
    ): bool {
        $kickoff = self::resolve($title, $creationDate);

        return null !== $kickoff && $kickoff < ($now ?? new DateTimeImmutable());
    }

    public static function isKickoffDayPast(
        string $title,
        DateTimeImmutable $creationDate,
        ?DateTimeImmutable $now = null,
    ): bool {
        $kickoff = self::resolve($title, $creationDate);

        if (null === $kickoff) {
            return false;
        }

        $startOfToday = ($now ?? new DateTimeImmutable())->setTime(0, 0);

        return $kickoff < $startOfToday;
    }
}