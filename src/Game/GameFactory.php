<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

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
        $messages = new GameMessageManager()->findGameMessageRecordsByGameId($game->gameId);
        $slots = new GameSlotManager()->findGameSlotRecordsByGameId($game->gameId);
        $gameUsers = new GameUserManager()->findGameUserRecordsByGameId($game->gameId);
        $users = new UserManager()->findUserRecordsByIds(array_column($gameUsers, 'telegramUserId'));

        return new GameBuilder($game, $messages, $slots, $gameUsers, $users, $addOns)->build();
    }
}
