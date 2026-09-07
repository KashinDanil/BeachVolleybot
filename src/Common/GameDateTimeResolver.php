<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;
use InvalidArgumentException;

final class GameDateTimeResolver
{
    /** Return date and time of the kickoff, or null if the title does not contain a time in the timezone of the venue. */
    public static function resolve(string $title, DateTimeImmutable $creationDate): ?DateTimeImmutable
    {
        $time = TimeExtractor::extract($title);

        if (null === $time) {
            return null;
        }

        // A title spells its kickoff as wall clock at the venue, so the anchor is read there
        // first — otherwise "Saturday" and the hour land on the server's calendar.
        $anchor = $creationDate->setTimezone(KnownVenues::findInTitleOrDefault($title)->timezone);
        $gameDate = GameDateResolver::resolve($title, $anchor) ?? $anchor;
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

    /** A kickoff day belongs to the venue, so the boundary is read on the kickoff's own clock. */
    public static function isKickoffDayPast(DateTimeImmutable $kickoff, ?DateTimeImmutable $now = null): bool
    {
        $venueToday = ($now ?? new DateTimeImmutable())
            ->setTimezone($kickoff->getTimezone())
            ->setTime(0, 0);

        return $kickoff < $venueToday;
    }
}
