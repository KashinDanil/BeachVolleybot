<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\PlayerInterface;

readonly class GroupSizeTelegramMessageBuilder extends DefaultTelegramMessageBuilder
{
    protected function plusCount(PlayerInterface $player, int $appearance): int
    {
        $number = $player->getNumber();

        if (str_contains($number, '-')) {
            $parts = explode('-', $number);

            return (int) $parts[1] - (int) $parts[0] + 1;
        }

        return 1;
    }
}