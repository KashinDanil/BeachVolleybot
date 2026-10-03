<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Roster;

use BeachVolleybot\Game\AddOns\GameAddOnInterface;
use BeachVolleybot\Game\AddOns\GameAddOnRegistry;
use BeachVolleybot\Game\AddOns\MergeConsecutiveSlotsAddOn;
use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Game\Models\PlayerInterface;

final readonly class Lineup
{
    /**
     * @param PlayerInterface[] $players
     * @param list<class-string<GameAddOnInterface>> $addOns
     */
    public function __construct(
        private array $players,
        private PlayerLimit $limit,
        private array $addOns = GAME_ADD_ONS,
    ) {
    }

    /**
     * @param PlayerInterface[] $players
     * @param list<class-string<GameAddOnInterface>> $addOns
     */
    public static function forSettings(array $players, GameSettings $settings, array $addOns = GAME_ADD_ONS): self
    {
        return new self($players, PlayerLimit::resolveLimit($players, $settings), $addOns);
    }

    public function hasLimit(): bool
    {
        return null !== $this->limit->threshold;
    }

    public function isReserve(PlayerInterface $row): bool
    {
        return $this->limit->isReserve($row);
    }

    /** @return list<PlayerInterface> */
    public function getRowsToRender(): array
    {
        if (!$this->hasLimit()) {
            return $this->players;
        }

        $slots = $this->arrangedSlots();

        if (!GameAddOnRegistry::isEnabled(MergeConsecutiveSlotsAddOn::class, $this->addOns)) {
            return $slots;
        }

        return $this->mergedBackIntoRows($slots);
    }

    public function getPlayingUserIds(): array
    {
        $playingSlots = array_filter($this->arrangedSlots(), fn(PlayerInterface $slot): bool => !$this->isReserve($slot));

        return array_map(
                static fn(PlayerInterface $slot): int => $slot->getTelegramUserId(),
                $playingSlots,
            )
                |> array_unique(...)
                |> array_values(...);
    }

    /** @return list<PlayerInterface> */
    private function arrangedSlots(): array
    {
        $slots = $this->expandPlayers();
        $slots = $this->promotePlayers($slots, $this->userIdsToPromote($slots));

        return $this->renumberPlayers($slots);
    }

    /** @return list<PlayerInterface> */
    private function expandPlayers(): array
    {
        $slots = [];

        foreach ($this->players as $player) {
            $position = $player->getPosition();

            for ($number = $position->first(); $number <= $position->last(); $number++) {
                $slots[] = $this->createNewPlayer($player, new Position($number));
            }
        }

        return $slots;
    }

    /**
     * @param PlayerInterface[] $slots
     * @param list<int> $userIdsToPromote
     *
     * @return list<PlayerInterface>
     */
    private function promotePlayers(array $slots, array $userIdsToPromote): array
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
     * @param PlayerInterface[] $slots
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
     * The game cannot be played without these: enough net bringers and ball holders — taken in
     * sign-up order, whichever the type — to equip every court that can be run, which is as many
     * courts as the scarcer of nets and balls allows.
     *
     * @param PlayerInterface[] $slots
     *
     * @return list<int>
     */
    private function equipmentCarrierUserIds(array $slots): array
    {
        $netsByUserId = $this->netsByUserId($slots);
        $volleyballsByUserId = $this->volleyballsByUserId($slots);
        $courts = min(array_sum($netsByUserId), array_sum($volleyballsByUserId));

        return array_values(array_unique([
            ...$this->holdersCovering($netsByUserId, $courts),
            ...$this->holdersCovering($volleyballsByUserId, $courts),
        ]));
    }

    /**
     * @param array<int, int> $equipmentByUserId
     *
     * @return list<int>
     */
    private function holdersCovering(array $equipmentByUserId, int $required): array
    {
        $holderUserIds = [];
        $carried = 0;

        foreach ($equipmentByUserId as $userId => $count) {
            if ($required <= $carried) {
                break;
            }

            $holderUserIds[] = $userId;
            $carried += $count;
        }

        return $holderUserIds;
    }

    /**
     * @param list<int> $userIds
     * @param PlayerInterface[] $arrangement
     */
    private function allPlaying(array $userIds, array $arrangement): bool
    {
        $playingUserIds = $this->playingUserIds($arrangement);

        return array_all($userIds, static fn(int $userId): bool => in_array($userId, $playingUserIds, true));
    }

    /**
     * @param PlayerInterface[] $slots
     *
     * @return array<int, int> nets per user, bringers only
     */
    private function netsByUserId(array $slots): array
    {
        return array_filter(array_map(
            static fn(PlayerInterface $slot): int => $slot->getNet(),
            $this->firstSlotByUserId($slots),
        ));
    }

    /**
     * @param PlayerInterface[] $slots
     *
     * @return array<int, int> volleyballs per user, holders only
     */
    private function volleyballsByUserId(array $slots): array
    {
        return array_filter(array_map(
            static fn(PlayerInterface $slot): int => $slot->getVolleyball(),
            $this->firstSlotByUserId($slots),
        ));
    }

    /**
     * Equipment is repeated on every slot a user holds, so counting it means counting one slot.
     *
     * @param PlayerInterface[] $slots
     *
     * @return array<int, PlayerInterface> keyed by user id, in slot order
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
     * @param PlayerInterface[] $slots
     *
     * @return list<int>
     */
    private function playingUserIds(array $slots): array
    {
        return array_map(
            static fn(PlayerInterface $slot): int => $slot->getTelegramUserId(),
            array_slice($slots, 0, $this->limit->threshold),
        );
    }

    /**
     * @param PlayerInterface[] $slots
     *
     * @return list<PlayerInterface>
     */
    private function renumberPlayers(array $slots): array
    {
        $renumbered = [];

        foreach (array_values($slots) as $index => $slot) {
            $renumbered[] = $this->createNewPlayer($slot, new Position($index + 1));
        }

        return $renumbered;
    }

    /**
     * Each side of the divider is merged on its own, so slots that straddle it stay two rows —
     * a single line cannot carry numbers from both sides.
     *
     * @param PlayerInterface[] $slots
     *
     * @return list<PlayerInterface>
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
     * @param PlayerInterface[] $slots
     *
     * @return list<PlayerInterface>
     */
    private function playingSlots(array $slots): array
    {
        return array_values(array_filter($slots, fn(PlayerInterface $slot): bool => !$this->limit->isReserve($slot)));
    }

    /**
     * @param PlayerInterface[] $slots
     *
     * @return list<PlayerInterface>
     */
    private function reserveSlots(array $slots): array
    {
        return array_values(array_filter($slots, $this->limit->isReserve(...)));
    }

    private function createNewPlayer(PlayerInterface $player, PositionInterface $position): Player
    {
        return new Player(
            telegramUserId: $player->getTelegramUserId(),
            position: $position,
            name: $player->getName(),
            link: $player->getLink(),
            volleyball: $player->getVolleyball(),
            net: $player->getNet(),
            time: $player->getTime(),
        );
    }
}
