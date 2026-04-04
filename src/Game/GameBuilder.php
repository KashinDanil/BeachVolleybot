<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Game\AddOns\GameAddOnInterface;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Game\Models\PlayerInterface;

readonly class GameBuilder
{

    /**
     * @param array<string, mixed> $gameRow
     * @param list<array<string, mixed>> $slotRows
     * @param list<array<string, mixed>> $gamePlayerRows
     * @param list<array<string, mixed>> $playerRows
     * @param list<class-string<GameAddOnInterface>> $addOns
     */
    public function __construct(
        private array $gameRow,
        private array $slotRows,
        private array $gamePlayerRows,
        private array $playerRows,
        private array $addOns = GAME_ADD_ONS,
    ) {
    }

    public function build(): GameInterface
    {
        return $this->applyAddOns(
            new Game(
                gameId: (int)$this->gameRow['game_id'],
                inlineQueryId: (string)$this->gameRow['inline_query_id'],
                inlineMessageId: (string)$this->gameRow['inline_message_id'],
                title: (string)$this->gameRow['title'],
                players: $this->buildPlayers(),
            )
        );
    }

    private function applyAddOns(GameInterface $game): GameInterface
    {
        foreach ($this->addOns as $addOnClass) {
            $game = new $addOnClass()->transform($game);
        }

        return $game;
    }

    /** @return PlayerInterface[] */
    private function buildPlayers(): array
    {
        $gamePlayersIndex = array_column($this->gamePlayerRows, null, 'telegram_user_id');
        $playersIndex = array_column($this->playerRows, null, 'telegram_user_id');

        $players = [];

        foreach ($this->slotRows as $slot) {
            $telegramUserId = $slot['telegram_user_id'];
            $players[] = $this->buildPlayer($slot, $gamePlayersIndex[$telegramUserId], $playersIndex[$telegramUserId]);
        }

        return $players;
    }

    private function buildPlayer(array $slot, array $gamePlayerRow, array $playerRow): Player
    {
        return new Player(
            telegramUserId: (int)$slot['telegram_user_id'],
            number: (string)$slot['position'],
            name: Player::buildName($playerRow['first_name'], $playerRow['last_name'] ?? null),
            link: Player::buildLink($playerRow['username'] ?? null),
            volleyball: (int)$gamePlayerRow['volleyball'],
            net: (int)$gamePlayerRow['net'],
            time: $gamePlayerRow['time'],
        );
    }

}
