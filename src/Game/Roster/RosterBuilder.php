<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Roster;

use BeachVolleybot\Game\GameSlotRecord;
use BeachVolleybot\Game\GameUserRecord;
use BeachVolleybot\Game\Models\User;
use BeachVolleybot\User\UserRecord;

final readonly class RosterBuilder
{
    /** @var array<int, GameUserRecord> */
    private array $gameUsersById;

    /** @var array<int, UserRecord> */
    private array $usersById;

    /**
     * @param list<GameSlotRecord> $slots
     * @param list<GameUserRecord> $gameUsers
     * @param list<UserRecord> $users names and links; leave out when only places and equipment matter
     */
    public function __construct(
        private array $slots,
        array $gameUsers,
        array $users = [],
    ) {
        $this->gameUsersById = array_column($gameUsers, null, 'telegramUserId');
        $this->usersById = array_column($users, null, 'telegramUserId');
    }

    /** @return list<User> one per slot, in slot order */
    public function build(): array
    {
        return array_map($this->buildUser(...), $this->slots);
    }

    private function buildUser(GameSlotRecord $slot): User
    {
        $gameUser = $this->gameUsersById[$slot->telegramUserId];
        $userRecord = $this->usersById[$slot->telegramUserId] ?? null;

        return new User(
            telegramUserId: $slot->telegramUserId,
            position: new Position($slot->position),
            name: $this->buildName($userRecord),
            link: User::buildLink($userRecord?->username),
            volleyball: $gameUser->volleyball,
            net: $gameUser->net,
            time: $gameUser->time,
        );
    }

    private function buildName(?UserRecord $userRecord): string
    {
        if (null === $userRecord) {
            return '';
        }

        return User::buildName($userRecord->firstName, $userRecord->lastName);
    }
}
