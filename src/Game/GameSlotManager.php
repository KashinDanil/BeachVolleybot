<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameSlotRepository;

readonly class GameSlotManager
{
    private GameSlotRepository $gameSlotRepository;

    public function __construct()
    {
        $this->gameSlotRepository = new GameSlotRepository(Connection::get());
    }

    /** @return list<GameSlotRecord> */
    public function findGameSlotRecordsByGameId(int $gameId): array
    {
        return array_map(GameSlotRecord::fromRow(...), $this->gameSlotRepository->findByGameId($gameId));
    }

    /** @return list<int> */
    public function findPositionsByUser(int $gameId, int $telegramUserId): array
    {
        return $this->gameSlotRepository->findPositionsByUser($gameId, $telegramUserId);
    }

    public function countSlots(int $gameId): int
    {
        return $this->gameSlotRepository->countByGameId($gameId);
    }

    public function addSlot(int $gameId, int $telegramUserId): void
    {
        $this->gameSlotRepository->create(
            $gameId,
            $telegramUserId,
            $this->gameSlotRepository->getNextPosition($gameId),
        );
    }

    public function deleteSlot(int $gameId, int $position): void
    {
        $this->gameSlotRepository->delete($gameId, $position);
    }
}
