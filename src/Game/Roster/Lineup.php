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
     * As soon as the limit would push any equipment carrier out, all of them move up.
     *
     * @param UserInterface[] $slots
     *
     * @return list<int>
     */
    private function userIdsToPromote(array $slots): array
    {
        $equipmentCarrierUserIds = $this->equipmentCarrierUserIds($slots);

        if ($this->allPlaying($equipmentCarrierUserIds, $slots)) {
            return [];
        }

        return $equipmentCarrierUserIds;
    }

    /**
     * The game cannot be played without these: every net bringer, and as many volleyball holders
     * as it takes to put a ball behind each net, taken in sign-up order.
     *
     * @param UserInterface[] $slots
     *
     * @return list<int>
     */
    private function equipmentCarrierUserIds(array $slots): array
    {
        $netsByUserId = $this->netsByUserId($slots);
        $netCount = array_sum($netsByUserId);
        $netHolderUserIds = array_keys($netsByUserId);
        $volleyballHolderUserIds = $this->holdersCovering($this->volleyballsByUserId($slots), $netCount);

        return array_values(array_unique([...$netHolderUserIds, ...$volleyballHolderUserIds]));
    }

    /**
     * @param array<int, int> $volleyballsByUserId
     *
     * @return list<int>
     */
    private function holdersCovering(array $volleyballsByUserId, int $netCount): array
    {
        $holderUserIds = [];
        $volleyballCount = 0;

        foreach ($volleyballsByUserId as $userId => $volleyballs) {
            if ($netCount <= $volleyballCount) {
                break;
            }

            $holderUserIds[] = $userId;
            $volleyballCount += $volleyballs;
        }

        return $holderUserIds;
    }

    /**
     * @param list<int> $userIds
     * @param UserInterface[] $arrangement
     */
    private function allPlaying(array $userIds, array $arrangement): bool
    {
        $playingUserIds = $this->playingUserIds($arrangement);

        return array_all($userIds, static fn(int $userId): bool => in_array($userId, $playingUserIds, true));
    }

    /**
     * @param UserInterface[] $slots
     *
     * @return array<int, int> nets per user, bringers only
     */
    private function netsByUserId(array $slots): array
    {
        return array_filter(array_map(
            static fn(UserInterface $slot): int => $slot->getNet(),
            $this->firstSlotByUserId($slots),
        ));
    }

    /**
     * @param UserInterface[] $slots
     *
     * @return array<int, int> volleyballs per user, holders only
     */
    private function volleyballsByUserId(array $slots): array
    {
        return array_filter(array_map(
            static fn(UserInterface $slot): int => $slot->getVolleyball(),
            $this->firstSlotByUserId($slots),
        ));
    }

    /**
     * Equipment is repeated on every slot a user holds, so counting it means counting one slot.
     *
     * @param UserInterface[] $slots
     *
     * @return array<int, UserInterface> keyed by user id, in slot order
     */
    private function firstSlotByUserId(array $slots): array
    {
        $firstSlots = [];

        foreach ($slots as $slot) {
            $firstSlots[$slot->getTelegramUserId()] ??= $slot;
        }

        return $firstSlots;
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
