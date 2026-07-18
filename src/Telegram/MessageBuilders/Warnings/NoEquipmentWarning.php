<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\UserInterface;

final class NoEquipmentWarning implements GameWarningInterface
{
    /**
     * @param UserInterface[] $users
     */
    public function check(array $users): ?string
    {
        $hasNet = array_any($users, static fn(UserInterface $user) => 0 < $user->getNet());
        $hasVolleyball = array_any($users, static fn(UserInterface $user) => 0 < $user->getVolleyball());

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
