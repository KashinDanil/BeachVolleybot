<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Roster;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\AddOns\GameAddOnInterface;
use BeachVolleybot\Game\AddOns\GameAddOnRegistry;
use BeachVolleybot\Game\AddOns\MergeConsecutiveSlotsAddOn;
use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Game\Models\UserInterface;

final readonly class Lineup
{
    /**
     * @param UserInterface[] $users
     * @param list<class-string<GameAddOnInterface>> $addOns
     */
    public function __construct(
        private array $users,
        private PlayerLimit $limit,
        private array $addOns = GAME_ADD_ONS,
    ) {
    }

    /** @return list<UserInterface> */
    public function getRowsToRender(): array
    {
        if (null === $this->limit->threshold) {
            return $this->users;
        }

        $slots = $this->expandUsers();
        $slots = $this->promoteUsers($slots, $this->userIdsToPromote($slots));
        $slots = $this->renumberUsers($slots);

        if (!GameAddOnRegistry::isEnabled(MergeConsecutiveSlotsAddOn::class, $this->addOns)) {
            return $slots;
        }

        return $this->mergedBackIntoRows($slots);
    }

    /** @return list<UserInterface> */
    private function expandUsers(): array
    {
        $slots = [];

        foreach ($this->users as $user) {
            $position = $user->getPosition();

            for ($number = $position->first(); $number <= $position->last(); $number++) {
                $slots[] = $this->createNewUser($user, new Position($number));
            }
        }

        return $slots;
    }

    /**
     * @param UserInterface[] $slots
     * @param list<int> $userIdsToPromote
     *
     * @return list<UserInterface>
     */
    private function promoteUsers(array $slots, array $userIdsToPromote): array
    {
        $front = [];
        $rest = [];

        foreach ($slots as $slot) {
            $userId = $slot->getTelegramUserId();

            if (
                in_array($userId, $userIdsToPromote, true) //It's a net bringer
                && !isset($front[$userId]) //It's the first entry of the user
            ) {
                $front[$userId] = $slot;
                continue;
            }

            $rest[] = $slot;
        }

        return [...$front, ...$rest];
    }

    /**
     * As soon as the limit would push any net bringer out, all of them move up
     *
     * @param UserInterface[] $slots
     *
     * @return list<int>
     */
    private function userIdsToPromote(array $slots): array
    {
        $netBringerUserIds = $this->netBringerUserIds($slots);
        $playingUserIds = $this->playingUserIds($slots);
        $anyPushedOut = array_any($netBringerUserIds, static fn(int $bringerId): bool => !in_array($bringerId, $playingUserIds, true));

        if (!$anyPushedOut) {
            return [];
        }

        return $netBringerUserIds;
    }

    /**
     * @param UserInterface[] $slots
     *
     * @return list<int>
     */
    private function netBringerUserIds(array $slots): array
    {
        $bringerIds = [];

        foreach ($slots as $slot) {
            $userId = $slot->getTelegramUserId();

            if (0 < $slot->getNet() && !in_array($userId, $bringerIds, true)) {
                $bringerIds[] = $userId;
            }
        }

        return $bringerIds;
    }

    /**
     * @param UserInterface[] $slots
     *
     * @return list<int>
     */
    private function playingUserIds(array $slots): array
    {
        return array_map(
            static fn(UserInterface $slot): int => $slot->getTelegramUserId(),
            array_slice($slots, 0, $this->limit->threshold),
        );
    }

    /**
     * @param UserInterface[] $slots
     *
     * @return list<UserInterface>
     */
    private function renumberUsers(array $slots): array
    {
        $renumbered = [];

        foreach (array_values($slots) as $index => $slot) {
            $renumbered[] = $this->createNewUser($slot, new Position($index + 1));
        }

        return $renumbered;
    }

    /**
     * Each side of the divider is merged on its own, so slots that straddle it stay two rows —
     * a single line cannot carry numbers from both sides.
     *
     * @param UserInterface[] $slots
     *
     * @return list<UserInterface>
     */
    private function mergedBackIntoRows(array $slots): array
    {
        $merger = new MergeConsecutiveSlotsAddOn();

        return [
            ...$merger->mergeConsecutive($this->playingSlots($slots)),
            ...$merger->mergeConsecutive($this->reserveSlots($slots)),
        ];
    }

    /**
     * @param UserInterface[] $slots
     *
     * @return list<UserInterface>
     */
    private function playingSlots(array $slots): array
    {
        return array_values(array_filter($slots, fn(UserInterface $slot): bool => !$this->limit->isReserve($slot)));
    }

    /**
     * @param UserInterface[] $slots
     *
     * @return list<UserInterface>
     */
    private function reserveSlots(array $slots): array
    {
        return array_values(array_filter($slots, $this->limit->isReserve(...)));
    }

    private function createNewUser(UserInterface $user, PositionInterface $position): User
    {
        return new User(
            telegramUserId: $user->getTelegramUserId(),
            position: $position,
            name: $user->getName(),
            link: $user->getLink(),
            volleyball: $user->getVolleyball(),
            net: $user->getNet(),
            time: $user->getTime(),
        );
    }
}
