<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\PlayerInterface;

interface GameWarningInterface
{
    /**
     * @param PlayerInterface[] $players
     */
    public function check(array $players): ?string;
}
