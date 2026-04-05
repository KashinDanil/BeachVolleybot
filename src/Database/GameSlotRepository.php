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

        return 0 < $result->rowCount();
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
}
