<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use DateTimeImmutable;
use InvalidArgumentException;

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

    public static function resolveOrFail(string $title, DateTimeImmutable $creationDate): DateTimeImmutable
    {
        return self::resolve($title, $creationDate)
            ?? throw new InvalidArgumentException("Game title carries no kickoff time: $title");
    }

    public static function isKickoffPast(DateTimeImmutable $kickoff, ?DateTimeImmutable $now = null): bool
    {
        return $kickoff < ($now ?? new DateTimeImmutable());
    }

    public static function isKickoffDayPast(DateTimeImmutable $kickoff, ?DateTimeImmutable $now = null): bool
    {
        return $kickoff < ($now ?? new DateTimeImmutable())->setTime(0, 0);
    }
}
