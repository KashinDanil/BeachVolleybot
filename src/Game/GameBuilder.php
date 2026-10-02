<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Game\AddOns\GameAddOnApplier;
use BeachVolleybot\Game\AddOns\GameAddOnInterface;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Roster\RosterBuilder;
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
            players: new RosterBuilder($this->slots, $this->gameUsers, $this->users)->build(),
            createdAt: $this->gameRecord->createdAt,
            kickoffAt: $this->gameRecord->kickoffAt,
            venueName: $this->gameRecord->venueName,
            location: $this->gameRecord->location,
            settings: $this->gameRecord->settings,
        );

        return GameAddOnApplier::apply($game, $this->addOns);
    }
}
