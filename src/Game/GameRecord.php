<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use DateTimeImmutable;

readonly class GameRecord
{
    public function __construct(
        public int $gameId,
        public string $inlineMessageId,
        public int $createdBy,
        public string $title,
        public DateTimeImmutable $createdAt,
    ) {
    }
}