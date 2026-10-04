<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Roster;

use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Validator\Rules\Game\MinimumPlayersPerNetRule;

final readonly class PlayerLimit
{
    public function __construct(
        public ?int $threshold,
    ) {
    }

    /** @param PlayerInterface[] $players */
    public static function resolveLimit(array $players, GameSettings $settings): self
    {
        if (null === $settings->playersPerNet) {
            return new self(null);
        }

        $courts = min(self::countNets($players), self::countVolleyballs($players));

        if (0 === $courts) {
            return new self(null);
        }

        return new self(max(MinimumPlayersPerNetRule::MINIMUM, $settings->playersPerNet) * $courts);
    }

    /** A row plays if it starts inside the limit, so a merged range is judged by its first slot. */
    public function isReserve(PlayerInterface $player): bool
    {
        return null !== $this->threshold && $this->threshold < $player->getPosition()->first();
    }

    /** @param PlayerInterface[] $players */
    private static function countNets(array $players): int
    {
        return array_sum(array_map(
            static fn(PlayerInterface $player): int => $player->getNet(),
            self::firstEntryByUserId($players),
        ));
    }

    /** @param PlayerInterface[] $players */
    private static function countVolleyballs(array $players): int
    {
        return array_sum(array_map(
            static fn(PlayerInterface $player): int => $player->getVolleyball(),
            self::firstEntryByUserId($players),
        ));
    }

    /**
     * Equipment is repeated on every slot a user holds, so it is counted from one entry per user.
     *
     * @param PlayerInterface[] $players
     *
     * @return array<int, PlayerInterface>
     */
    private static function firstEntryByUserId(array $players): array
    {
        $firstEntries = [];

        foreach ($players as $player) {
            $firstEntries[$player->getTelegramUserId()] ??= $player;
        }

        return $firstEntries;
    }
}
