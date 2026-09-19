<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Roster;

use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\Models\UserInterface;
use BeachVolleybot\Validator\Rules\MinimumPlayersPerNetRule;

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

        $nets = self::countNets($users);

        if (0 === $nets) {
            return new self(null);
        }

        return new self(max(MinimumPlayersPerNetRule::MINIMUM, $settings->playersPerNet) * $nets);
    }

    /** A row plays if it starts inside the limit, so a merged range is judged by its first slot. */
    public function isReserve(UserInterface $user): bool
    {
        return null !== $this->threshold && $this->threshold < $user->getPosition()->first();
    }

    /**
     * @param UserInterface[] $users
     */
    private static function countNets(array $users): int
    {
        $netsByUser = [];

        foreach ($users as $user) {
            $netsByUser[$user->getTelegramUserId()] ??= $user->getNet();
        }

        return array_sum($netsByUser);
    }
}
