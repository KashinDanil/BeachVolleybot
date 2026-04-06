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
