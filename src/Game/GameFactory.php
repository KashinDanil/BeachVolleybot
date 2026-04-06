<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\PlayerRepository;
use BeachVolleybot\Game\Models\GameInterface;
use RuntimeException;

final class GameFactory
{
    public static function fromGameId(int $gameId): GameInterface
    {
        $gameRow = new GameRepository(Connection::get())->findById($gameId);

        if (null === $gameRow) {
            throw new RuntimeException("Game not found: $gameId");
        }

        return self::buildFromRow($gameRow);
    }

    public static function fromInlineMessageId(string $inlineMessageId): GameInterface
    {
        $gameRow = new GameRepository(Connection::get())->findByInlineMessageId($inlineMessageId);

        if (null === $gameRow) {
            throw new RuntimeException("Game not found by inline_message_id: $inlineMessageId");
        }

        return self::buildFromRow($gameRow);
    }

    public static function create(NewGameData $data): int
    {
        $db = Connection::get();

        new PlayerRepository($db)->upsert(
            $data->playerRow['telegram_user_id'],
            $data->playerRow['first_name'],
            $data->playerRow['last_name'],
            $data->playerRow['username'],
        );

        $gameId = new GameRepository($db)->create(
            $data->gameRow['title'],
            $data->playerRow['telegram_user_id'],
            $data->gameRow['inline_message_id'],
            $data->gameRow['inline_query_id'],
            $data->gameRow['location'],
        );

        new GamePlayerRepository($db)->create(
            $gameId,
            $data->gamePlayerRow['telegram_user_id'],
            $data->gamePlayerRow['time'],
            $data->gamePlayerRow['volleyball'],
            $data->gamePlayerRow['net'],
        );

        new GameSlotRepository($db)->create(
            $gameId,
            $data->slotRow['telegram_user_id'],
            $data->slotRow['position'],
        );

        return $gameId;
    }

    private static function buildFromRow(array $gameRow): GameInterface
    {
        $db = Connection::get();
        $gameId = (int)$gameRow['game_id'];

        $slotRows = new GameSlotRepository($db)->findByGameId($gameId);
        $gamePlayerRows = new GamePlayerRepository($db)->findByGameId($gameId);
        $playerRows = new PlayerRepository($db)->findByIds(array_column($gamePlayerRows, 'telegram_user_id'));

        return new GameBuilder($gameRow, $slotRows, $gamePlayerRows, $playerRows)->build();
    }
}