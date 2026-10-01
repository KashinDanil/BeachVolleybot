<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameMessageRepository;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\User\UserManager;
use RuntimeException;

final class GameFactory
{
    public static function fromGameId(int $gameId): GameInterface
    {
        return self::tryFromGameId($gameId) ?? throw new RuntimeException("Game not found: $gameId");
    }

    public static function tryFromGameId(int $gameId, array $addOns = GAME_ADD_ONS): ?GameInterface
    {
        $gameRecord = new GameManager()->findGameRecordById($gameId);

        if (null === $gameRecord) {
            return null;
        }

        return self::fromRecord($gameRecord, $addOns);
    }

    public static function fromRecord(GameRecord $game, array $addOns = GAME_ADD_ONS): GameInterface
    {
        $db = Connection::get();

        $messages = new GameMessageRepository($db)->findByGameId($game->gameId);
        $slotRows = new GameSlotRepository($db)->findByGameId($game->gameId);
        $gameUserRows = new GameUserRepository($db)->findByGameId($game->gameId);
        $users = new UserManager()->findUserRecordsByIds(array_column($gameUserRows, 'telegram_user_id'));

        return new GameBuilder($game, $messages, $slotRows, $gameUserRows, $users, $addOns)->build();
    }
}
