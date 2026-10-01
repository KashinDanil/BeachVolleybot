<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Game\AddOns\GameAddOnApplier;
use BeachVolleybot\Game\AddOns\GameAddOnInterface;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Game\Roster\Position;
use BeachVolleybot\User\UserRecord;

readonly class GameBuilder
{
    /**
     * @param list<GameMessageRecord> $messages
     * @param list<GameSlotRecord> $slots
     * @param list<GameUserRecord> $gameUsers
     * @param list<UserRecord> $users
     * @param list<class-string<GameAddOnInterface>> $addOns
     */
    public function __construct(
        private GameRecord $gameRecord,
        private array $messages,
        private array $slots,
        private array $gameUsers,
        private array $users,
        private array $addOns = GAME_ADD_ONS,
    ) {
    }

    public function build(): GameInterface
    {
        $game = new Game(
            gameId: $this->gameRecord->gameId,
            gameKey: $this->gameRecord->gameKey,
            messages: $this->messages,
            title: $this->gameRecord->title,
            users: $this->buildUsers(),
            createdAt: $this->gameRecord->createdAt,
            kickoffAt: $this->gameRecord->kickoffAt,
            venueName: $this->gameRecord->venueName,
            location: $this->gameRecord->location,
            settings: $this->gameRecord->settings,
        );

        return GameAddOnApplier::apply($game, $this->addOns);
    }

    /** @return User[] */
    private function buildUsers(): array
    {
        $gameUsersIndex = array_column($this->gameUsers, null, 'telegramUserId');
        $usersIndex = array_column($this->users, null, 'telegramUserId');

        $users = [];

        foreach ($this->slots as $slot) {
            $telegramUserId = $slot->telegramUserId;
            $users[] = $this->buildUser($slot, $gameUsersIndex[$telegramUserId], $usersIndex[$telegramUserId]);
        }

        return $users;
    }

    private function buildUser(GameSlotRecord $slot, GameUserRecord $gameUser, UserRecord $userRecord): User
    {
        return new User(
            telegramUserId: $slot->telegramUserId,
            position: new Position($slot->position),
            name: User::buildName($userRecord->firstName, $userRecord->lastName),
            link: User::buildLink($userRecord->username),
            volleyball: $gameUser->volleyball,
            net: $gameUser->net,
            time: $gameUser->time,
        );
    }
}
