<?php

declare(strict_types=1);

namespace BeachVolleybot\Notifications;

use BeachVolleybot\Game\GameSettings;

final readonly class LineupSnapshot
{
    /** @param ?list<int> $playingUserIds null when the game has no divider */
    public function __construct(
        public int $gameId,
        public GameSettings $settings,
        public ?array $playingUserIds,
    ) {
    }
}
