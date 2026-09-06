<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Game\AddOns\GameAddOnApplier;
use BeachVolleybot\Game\AddOns\GameAddOnInterface;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Telegram\Messages\Targets\GameMessageTarget;
use DateTimeImmutable;

readonly class GameBuilder
{
    /**
     * @param array<string, mixed> $gameRow
     * @param list<GameMessageTarget> $messageTargets
     * @param list<array<string, mixed>> $slotRows
     * @param list<array<string, mixed>> $gameUserRows
     * @param list<array<string, mixed>> $userRows
     * @param list<class-string<GameAddOnInterface>> $addOns
     */
    public function __construct(
        private array $gameRow,
        private array $messageTargets,
        private array $slotRows,
        private array $gameUserRows,
        private array $userRows,
        private array $addOns = GAME_ADD_ONS,
    ) {
    }

    public function build(): GameInterface
    {
        $title = (string)$this->gameRow['title'];

        $game = new Game(
            gameId: (int)$this->gameRow['game_id'],
            gameKey: (string)$this->gameRow['game_key'],
            messageTargets: $this->messageTargets,
            title: $title,
            users: $this->buildUsersFromRows(),
            createdAt: new DateTimeImmutable((string)$this->gameRow['created_at']),
            kickoffAt: new DateTimeImmutable((string)$this->gameRow['kickoff_at']),
            venueName: $this->gameRow['venue_name'] ?? null,
            location: $this->gameRow['location'] ?? null,
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
