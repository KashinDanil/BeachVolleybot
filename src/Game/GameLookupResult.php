<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

readonly class GameLookupResult
{
    public function __construct(
        public int $gameId,
        public string $inlineMessageId,
    ) {
    }
}
