<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\PlayerInterface;

final class NoEquipmentWarning implements GameWarningInterface
{
    /**
     * @param PlayerInterface[] $players
     */
    public function check(array $players): ?string
    {
        $hasNet = array_any($players, static fn(PlayerInterface $player) => 0 < $player->getNet());
        $hasVolleyball = array_any($players, static fn(PlayerInterface $player) => 0 < $player->getVolleyball());

        if ($hasNet && $hasVolleyball) {
            return null;
        }

        $missing = [];
        if (!$hasNet) {
            $missing[] = 'a net';
        }

        if (!$hasVolleyball) {
            $missing[] = 'a volleyball';
        }

        return 'Someone needs to bring ' . implode(' and ', $missing);
    }
}
