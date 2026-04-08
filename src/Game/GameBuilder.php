<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\TimeExtractor;
use BeachVolleybot\Game\AddOns\GameAddOnInterface;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Game\Models\PlayerInterface;

readonly class GameBuilder
{
    private const int UNPERSISTED_GAME_ID = 0;

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

    public static function buildFromNewGameData(NewGameData $data): GameInterface
    {
        $player = new Player(
            telegramUserId: $data->telegramUserId,
            number: (string)NewGameData::INITIAL_POSITION,
            name: Player::buildName($data->firstName, $data->lastName),
            link: Player::buildLink($data->username),
            volleyball: NewGameData::INITIAL_VOLLEYBALL,
            net: NewGameData::INITIAL_NET,
            time: TimeExtractor::extract($data->title),
        );

        $game = new Game(
            gameId: self::UNPERSISTED_GAME_ID,
            inlineQueryId: $data->inlineQueryId,
            inlineMessageId: $data->inlineMessageId,
            title: $data->title,
            players: [$player],
        );

        return (new self([], [], [], []))->applyAddOns($game);
    }

    public function build(): GameInterface
    {
        $title = (string)$this->gameRow['title'];

        $game = new Game(
            gameId: (int)$this->gameRow['game_id'],
            inlineQueryId: (string)$this->gameRow['inline_query_id'],
            inlineMessageId: (string)$this->gameRow['inline_message_id'],
            title: $title,
            players: $this->buildPlayersFromRows(),
            location: $this->gameRow['location'] ?? null,
        );

        return $this->applyAddOns($game);
    }

    private function applyAddOns(Game $game): GameInterface
    {
        $game->init();

        foreach ($this->addOns as $addOnClass) {
            new $addOnClass()->applyTo($game);
        }

        return $game;
    }

    /** @return PlayerInterface[] */
    private function buildPlayersFromRows(): array
    {
        $gamePlayersIndex = array_column($this->gamePlayerRows, null, 'telegram_user_id');
        $playersIndex = array_column($this->playerRows, null, 'telegram_user_id');

        $players = [];

        foreach ($this->slotRows as $slot) {
            $telegramUserId = $slot['telegram_user_id'];
            $players[] = $this->buildPlayerFromRow($slot, $gamePlayersIndex[$telegramUserId], $playersIndex[$telegramUserId]);
        }

        return $players;
    }

    private function buildPlayerFromRow(array $slot, array $gamePlayerRow, array $playerRow): Player
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
