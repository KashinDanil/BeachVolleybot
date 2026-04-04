<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use Medoo\Medoo;

readonly class GamePlayerRepository
{
    public function __construct(
        private Medoo $db,
    ) {
    }

    public function create(int $gameId, int $telegramUserId, ?string $time = null): void
    {
        $this->db->insert('game_players', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'time' => $time,
        ]);
    }

    public function findByGameAndPlayer(int $gameId, int $telegramUserId): ?array
    {
        return $this->db->get('game_players', '*', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]) ?: null;
    }

    public function findByGameId(int $gameId): array
    {
        return $this->db->select('game_players', '*', ['game_id' => $gameId]);
    }

    public function delete(int $gameId, int $telegramUserId): bool
    {
        $result = $this->db->delete('game_players', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);

        return 0 < $result->rowCount();
    }

    public function incrementVolleyball(int $gameId, int $telegramUserId): void
    {
        $this->db->update('game_players', ['volleyball[+]' => 1], [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);
    }

    public function decrementVolleyball(int $gameId, int $telegramUserId): void
    {
        $this->db->pdo->prepare(
            'UPDATE game_players SET volleyball = MAX(0, volleyball - 1) WHERE game_id = :game_id AND telegram_user_id = :telegram_user_id'
        )->execute([':game_id' => $gameId, ':telegram_user_id' => $telegramUserId]);
    }

    public function incrementNet(int $gameId, int $telegramUserId): void
    {
        $this->db->update('game_players', ['net[+]' => 1], [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);
    }

    public function decrementNet(int $gameId, int $telegramUserId): void
    {
        $this->db->pdo->prepare(
            'UPDATE game_players SET net = MAX(0, net - 1) WHERE game_id = :game_id AND telegram_user_id = :telegram_user_id'
        )->execute([':game_id' => $gameId, ':telegram_user_id' => $telegramUserId]);
    }
}