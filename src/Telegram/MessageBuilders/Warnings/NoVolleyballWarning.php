<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\PlayerInterface;

final class NoVolleyballWarning implements GameWarningInterface
{
    /**
     * @param PlayerInterface[] $players
     */
    public function check(array $players): ?string
    {
        if (array_any($players, static fn(PlayerInterface $player) => 0 < $player->getVolleyball())) {
            return null;
        }

        return 'A volleyball is needed';
    }
}
