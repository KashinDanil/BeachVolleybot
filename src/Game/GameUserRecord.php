<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Database\Timestamp;
use DateTimeImmutable;

readonly class GameUserRecord
{
    public function __construct(
        public int $gameId,
        public int $telegramUserId,
        public string $time,
        public int $volleyball,
        public int $net,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int)$row['game_id'],
            (int)$row['telegram_user_id'],
            (string)$row['time'],
            (int)$row['volleyball'],
            (int)$row['net'],
            Timestamp::parse((string)$row['created_at']),
            Timestamp::parse((string)$row['updated_at']),
        );
    }
}
