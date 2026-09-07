<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Database\Timestamp;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;

readonly class GameRecord
{
    public function __construct(
        public int $gameId,
        public string $gameKey,
        public int $createdBy,
        public string $title,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $kickoffAt,
        public ?string $venueName = null,
        public ?string $location = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int)$row['game_id'],
            (string)$row['game_key'],
            (int)$row['created_by'],
            (string)$row['title'],
            Timestamp::parse((string)$row['created_at']),
            self::venueTime((string)$row['kickoff_at'], $row['venue_name'] ?? null),
            $row['venue_name'] ?? null,
            $row['location'] ?? null,
        );
    }

    /** From here on the kickoff carries the venue's clock, so nothing downstream needs a timezone. */
    private static function venueTime(string $kickoffAt, ?string $venueName): DateTimeImmutable
    {
        return Timestamp::parse($kickoffAt)->setTimezone(KnownVenues::findByNameOrDefault($venueName)->timezone);
    }
}
