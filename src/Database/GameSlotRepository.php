<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use Medoo\Medoo;

readonly class GameSlotRepository
{
    public function __construct(
        private Medoo $db,
    ) {
    }

    public function create(int $gameId, int $telegramUserId, int $position): void
    {
        $this->db->insert('game_slots', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'position' => $position,
        ]);
    }

    public function findByGameId(int $gameId): array
    {
        return $this->db->select('game_slots', '*', [
            'game_id' => $gameId,
            'ORDER' => ['position' => 'ASC'],
        ]);
    }

    public function findByPlayer(int $gameId, int $telegramUserId): array
    {
        return $this->db->select('game_slots', '*', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);
    }

    public function delete(int $gameId, int $position): bool
    {
        $result = $this->db->delete('game_slots', [
            'game_id' => $gameId,
            'position' => $position,
        ]);

        if (0 < $result->rowCount()) {
            $this->decrementPositionsAbove($gameId, $position);

            return true;
        }

        return false;
    }

    public function findPositionsByPlayer(int $gameId, int $telegramUserId): array
    {
        return array_map('intval', $this->db->select('game_slots', 'position', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]));
    }

    public function deleteByPlayer(int $gameId, int $telegramUserId): int
    {
        return $this->db->delete('game_slots', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ])->rowCount();
    }

    public function getNextPosition(int $gameId): int
    {
        $max = $this->db->max('game_slots', 'position', ['game_id' => $gameId]);

        return null === $max ? 1 : (int)$max + 1;
    }

    private function decrementPositionsAbove(int $gameId, int $deletedPosition): void
    {
        $statement = $this->db->pdo->prepare( //Because commands are processed sequentially, we can do that safely without worrying about concurrency issues.
            'UPDATE game_slots SET position = position - 1 WHERE game_id = :game_id AND position > :deleted_position'
        );
        $statement->execute([':game_id' => $gameId, ':deleted_position' => $deletedPosition]);
    }
}
