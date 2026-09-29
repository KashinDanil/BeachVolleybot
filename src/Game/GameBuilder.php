<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Game\AddOns\GameAddOnApplier;
use BeachVolleybot\Game\AddOns\GameAddOnInterface;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Game\Roster\Position;
use BeachVolleybot\Telegram\Messages\GameMessage;
use BeachVolleybot\User\UserRecord;

readonly class GameBuilder
{
    /**
     * @param list<GameMessage> $messages
     * @param list<array<string, mixed>> $slotRows
     * @param list<array<string, mixed>> $gameUserRows
     * @param list<UserRecord> $users
     * @param list<class-string<GameAddOnInterface>> $addOns
     */
    public function __construct(
        private GameRecord $gameRecord,
        private array $messages,
        private array $slotRows,
        private array $gameUserRows,
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
        $gameUsersIndex = array_column($this->gameUserRows, null, 'telegram_user_id');
        $usersIndex = array_column($this->users, null, 'telegramUserId');

        $users = [];

        foreach ($this->slotRows as $slot) {
            $telegramUserId = $slot['telegram_user_id'];
            $users[] = $this->buildUser($slot, $gameUsersIndex[$telegramUserId], $usersIndex[$telegramUserId]);
        }

        return $users;
    }

    private function buildUser(array $slot, array $gameUserRow, UserRecord $userRecord): User
    {
        return new User(
            telegramUserId: (int)$slot['telegram_user_id'],
            position: new Position((int)$slot['position']),
            name: User::buildName($userRecord->firstName, $userRecord->lastName),
            link: User::buildLink($userRecord->username),
            volleyball: (int)$gameUserRow['volleyball'],
            net: (int)$gameUserRow['net'],
            time: $gameUserRow['time'],
        );
    }
}
