<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\UserInterface;

interface GameWarningInterface
{
    /**
     * @param UserInterface[] $users
     */
    public function check(array $users): ?string;
}
