<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Game\Roster\Position;
use BeachVolleybot\Game\Roster\PositionInterface;
use BeachVolleybot\Game\Roster\PositionRange;

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
        $game->players = $this->mergeConsecutive($game->players);
        $game->telegramMessageBuilder->override('plusCount', self::plusCount(...));
    }

    private static function plusCount(PlayerInterface $player, int $appearance): int
    {
        return $player->getPosition()->slotCount();
    }

    /**
     * @param PlayerInterface[] $players
     *
     * @return list<PlayerInterface>
     */
    public function mergeConsecutive(array $players): array
    {
        $groups = $this->groupConsecutive($players);

        return array_map($this->mergeGroup(...), $groups);
    }

    /**
     * @param PlayerInterface[] $players
     *
     * @return list<PlayerInterface[]>
     */
    private function groupConsecutive(array $players): array
    {
        $groups = [];
        $previousUserId = null;

        foreach ($players as $player) {
            if ($player->getTelegramUserId() === $previousUserId) {
                $groups[array_key_last($groups)][] = $player;
            } else {
                $groups[] = [$player];
                $previousUserId = $player->getTelegramUserId();
            }
        }

        return $groups;
    }

    /** @param PlayerInterface[] $group */
    private function mergeGroup(array $group): Player
    {
        $first = $group[0];
        $last = $group[array_key_last($group)];

        return new Player(
            telegramUserId: $first->getTelegramUserId(),
            position: $this->mergePositions($first->getPosition(), $last->getPosition()),
            name: $first->getName(),
            link: $first->getLink(),
            volleyball: $first->getVolleyball(),
            net: $first->getNet(),
            time: $first->getTime(),
        );
    }

    private function mergePositions(PositionInterface $first, PositionInterface $last): PositionInterface
    {
        $firstSlot = $first->first();
        $lastSlot = $last->last();

        if ($firstSlot === $lastSlot) {
            return new Position($firstSlot);
        }

        return new PositionRange($firstSlot, $lastSlot);
    }
}
