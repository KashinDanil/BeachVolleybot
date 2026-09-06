<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

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
            new DateTimeImmutable((string)$row['created_at']),
            new DateTimeImmutable((string)$row['kickoff_at']),
            $row['venue_name'] ?? null,
            $row['location'] ?? null,
        );
    }
}
