<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Game\Models\UserInterface;

/**
 * Merges consecutive slots belonging to the same user into a single entry.
 *
 * Before: 1. Alice, 2. Alice, 3. Bob
 * After: 1-2. Alice, 3. Bob
 */
final class MergeConsecutiveSlotsAddOn implements GameAddOnInterface
{
    public function applyTo(Game $game): void
    {
        $game->users = $this->mergeConsecutive($game->users);
        $game->telegramMessageBuilder->override('plusCount', self::plusCount(...));
    }

    private static function plusCount(UserInterface $user, int $appearance): int
    {
        $number = $user->getNumber();

        if (str_contains($number, '-')) {
            $parts = explode('-', $number);

            return (int)$parts[1] - (int)$parts[0] + 1;
        }

        return 1;
    }

    /**
     * @param UserInterface[] $users
     *
     * @return UserInterface[]
     */
    private function mergeConsecutive(array $users): array
    {
        $groups = $this->groupConsecutive($users);

        return array_map($this->mergeGroup(...), $groups);
    }

    /**
     * @param UserInterface[] $users
     *
     * @return list<UserInterface[]>
     */
    private function groupConsecutive(array $users): array
    {
        $groups = [];
        $previousUserId = null;

        foreach ($users as $user) {
            if ($user->getTelegramUserId() === $previousUserId) {
                $groups[array_key_last($groups)][] = $user;
            } else {
                $groups[] = [$user];
                $previousUserId = $user->getTelegramUserId();
            }
        }

        return $groups;
    }

    /** @param UserInterface[] $group */
    private function mergeGroup(array $group): User
    {
        $first = $group[0];
        $last = $group[array_key_last($group)];

        return new User(
            telegramUserId: $first->getTelegramUserId(),
            number: $this->buildNumber($first, $last),
            name: $first->getName(),
            link: $first->getLink(),
            volleyball: $first->getVolleyball(),
            net: $first->getNet(),
            time: $first->getTime(),
        );
    }

    private function buildNumber(UserInterface $first, UserInterface $last): string
    {
        if ($first === $last) {
            return $first->getNumber();
        }

        return $first->getNumber() . '-' . $last->getNumber();
    }
}
