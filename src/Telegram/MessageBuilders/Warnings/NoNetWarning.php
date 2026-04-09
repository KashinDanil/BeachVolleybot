<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\PlayerInterface;

final class NoNetWarning implements GameWarningInterface
{
    /**
     * @param PlayerInterface[] $players
     */
    public function check(array $players): ?string
    {
        if (array_any($players, static fn(PlayerInterface $player) => 0 < $player->getNet())) {
            return null;
        }

        return 'Someone needs to bring a net';
    }
}
