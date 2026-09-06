<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Game\AddOns\GameAddOnApplier;
use BeachVolleybot\Game\AddOns\GameAddOnInterface;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Telegram\Messages\Targets\GameMessageTarget;

readonly class GameBuilder
{
    /**
     * @param list<GameMessageTarget> $messageTargets
     * @param list<array<string, mixed>> $slotRows
     * @param list<array<string, mixed>> $gameUserRows
     * @param list<array<string, mixed>> $userRows
     * @param list<class-string<GameAddOnInterface>> $addOns
     */
    public function __construct(
        private GameRecord $gameRecord,
        private array $messageTargets,
        private array $slotRows,
        private array $gameUserRows,
        private array $userRows,
        private array $addOns = GAME_ADD_ONS,
    ) {
    }

    public function build(): GameInterface
    {
        $game = new Game(
            gameId: $this->gameRecord->gameId,
            gameKey: $this->gameRecord->gameKey,
            messageTargets: $this->messageTargets,
            title: $this->gameRecord->title,
            users: $this->buildUsersFromRows(),
            createdAt: $this->gameRecord->createdAt,
            kickoffAt: $this->gameRecord->kickoffAt,
            venueName: $this->gameRecord->venueName,
            location: $this->gameRecord->location,
        );

        return GameAddOnApplier::apply($game, $this->addOns);
    }

    /** @return User[] */
    private function buildUsersFromRows(): array
    {
        $gameUsersIndex = array_column($this->gameUserRows, null, 'telegram_user_id');
        $usersIndex = array_column($this->userRows, null, 'telegram_user_id');

        $users = [];

        foreach ($this->slotRows as $slot) {
            $telegramUserId = $slot['telegram_user_id'];
            $users[] = $this->buildUserFromRow($slot, $gameUsersIndex[$telegramUserId], $usersIndex[$telegramUserId]);
        }

        return $users;
    }

    private function buildUserFromRow(array $slot, array $gameUserRow, array $userRow): User
    {
        return new User(
            telegramUserId: (int)$slot['telegram_user_id'],
            number: (string)$slot['position'],
            name: User::buildName($userRow['first_name'], $userRow['last_name'] ?? null),
            link: User::buildLink($userRow['username'] ?? null),
            volleyball: (int)$gameUserRow['volleyball'],
            net: (int)$gameUserRow['net'],
            time: $gameUserRow['time'],
        );
    }
}
