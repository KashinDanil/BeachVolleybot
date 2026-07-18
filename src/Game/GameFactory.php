<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameInlineMessageRepository;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Game\Models\GameInterface;
use RuntimeException;

final class GameFactory
{
    public static function fromGameId(int $gameId): GameInterface
    {
        return self::tryFromGameId($gameId) ?? throw new RuntimeException("Game not found: $gameId");
    }

    public static function tryFromGameId(int $gameId, array $addOns = GAME_ADD_ONS): ?GameInterface
    {
        $gameRow = new GameRepository(Connection::get())->findById($gameId);

        if (null === $gameRow) {
            return null;
        }

        return self::buildFromRow($gameRow, $addOns);
    }

    private static function buildFromRow(array $gameRow, array $addOns = GAME_ADD_ONS): GameInterface
    {
        $db = Connection::get();
        $gameId = (int)$gameRow['game_id'];

        $inlineMessageIds = new GameInlineMessageRepository($db)->findInlineMessageIdsByGameId($gameId);
        $slotRows = new GameSlotRepository($db)->findByGameId($gameId);
        $gameUserRows = new GameUserRepository($db)->findByGameId($gameId);
        $userRows = new UserRepository($db)->findByIds(array_column($gameUserRows, 'telegram_user_id'));

        return new GameBuilder($gameRow, $inlineMessageIds, $slotRows, $gameUserRows, $userRows, $addOns)->build();
    }
}
