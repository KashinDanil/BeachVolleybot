<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameUserRepository;

readonly class GameUserManager
{
    private GameUserRepository $gameUserRepository;

    public function __construct()
    {
        $this->gameUserRepository = new GameUserRepository(Connection::get());
    }

    public function findGameUserRecord(int $gameId, int $telegramUserId): ?GameUserRecord
    {
        $row = $this->gameUserRepository->findByGameUser($gameId, $telegramUserId);

        return null !== $row ? GameUserRecord::fromRow($row) : null;
    }

    /** @return list<GameUserRecord> */
    public function findGameUserRecordsByGameId(int $gameId): array
    {
        return array_map(GameUserRecord::fromRow(...), $this->gameUserRepository->findByGameId($gameId));
    }

    public function isUserInGame(int $gameId, int $telegramUserId): bool
    {
        return $this->gameUserRepository->exists($gameId, $telegramUserId);
    }

    public function createGameUser(int $gameId, int $telegramUserId, string $time, int $volleyball = 0, int $net = 0): void
    {
        $this->gameUserRepository->create($gameId, $telegramUserId, $time, $volleyball, $net);
    }

    public function deleteGameUser(int $gameId, int $telegramUserId): void
    {
        $this->gameUserRepository->delete($gameId, $telegramUserId);
    }

    public function incrementNet(int $gameId, int $telegramUserId): bool
    {
        return $this->gameUserRepository->incrementNet($gameId, $telegramUserId);
    }

    public function decrementNet(int $gameId, int $telegramUserId): bool
    {
        return $this->gameUserRepository->decrementNet($gameId, $telegramUserId);
    }

    public function incrementVolleyball(int $gameId, int $telegramUserId): bool
    {
        return $this->gameUserRepository->incrementVolleyball($gameId, $telegramUserId);
    }

    public function decrementVolleyball(int $gameId, int $telegramUserId): bool
    {
        return $this->gameUserRepository->decrementVolleyball($gameId, $telegramUserId);
    }

    public function updateTime(int $gameId, int $telegramUserId, string $time): void
    {
        $this->gameUserRepository->updateTime($gameId, $telegramUserId, $time);
    }

    public function findEarliestTime(int $gameId): ?string
    {
        return $this->gameUserRepository->findEarliestTimeWithEquipment($gameId)
            ?? $this->gameUserRepository->findEarliestTime($gameId);
    }
}
