<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Database\Timestamp;
use DateTimeImmutable;

readonly class GameSlotRecord
{
    public function __construct(
        public int $gameId,
        public int $telegramUserId,
        public int $position,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int)$row['game_id'],
            (int)$row['telegram_user_id'],
            (int)$row['position'],
            Timestamp::parse((string)$row['created_at']),
        );
    }
}
