<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Roster;

use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\Models\UserInterface;
use BeachVolleybot\Validator\Rules\Game\MinimumPlayersPerNetRule;

final readonly class PlayerLimit
{
    public function __construct(
        public ?int $threshold,
    ) {
    }

    /** @param UserInterface[] $users */
    public static function resolveLimit(array $users, GameSettings $settings): self
    {
        if (null === $settings->playersPerNet) {
            return new self(null);
        }

        $courts = min(self::countNets($users), self::countVolleyballs($users));

        if (0 === $courts) {
            return new self(null);
        }

        return new self(max(MinimumPlayersPerNetRule::MINIMUM, $settings->playersPerNet) * $courts);
    }

    /** A row plays if it starts inside the limit, so a merged range is judged by its first slot. */
    public function isReserve(UserInterface $user): bool
    {
        return null !== $this->threshold && $this->threshold < $user->getPosition()->first();
    }

    /** @param UserInterface[] $users */
    private static function countNets(array $users): int
    {
        return array_sum(array_map(
            static fn(UserInterface $user): int => $user->getNet(),
            self::firstEntryByUserId($users),
        ));
    }

    /** @param UserInterface[] $users */
    private static function countVolleyballs(array $users): int
    {
        return array_sum(array_map(
            static fn(UserInterface $user): int => $user->getVolleyball(),
            self::firstEntryByUserId($users),
        ));
    }

    /**
     * Equipment is repeated on every slot a user holds, so it is counted from one entry per user.
     *
     * @param UserInterface[] $users
     *
     * @return array<int, UserInterface>
     */
    private static function firstEntryByUserId(array $users): array
    {
        $firstEntries = [];

        foreach ($users as $user) {
            $firstEntries[$user->getTelegramUserId()] ??= $user;
        }

        return $firstEntries;
    }
}
