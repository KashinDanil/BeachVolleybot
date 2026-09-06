<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use DateTimeImmutable;

readonly class PostedGame
{
    public function __construct(
        private(set) int $gameId,
        private(set) int $sentMessageId,
        private(set) DateTimeImmutable $kickoffAt,
    ) {
    }
}
