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

    public function create(int $gameId, int $telegramUserId, ?string $time = null, int $volleyball = 0, int $net = 0): void
    {
        $this->db->insert('game_players', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'time' => $time,
            'volleyball' => $volleyball,
            'net' => $net,
        ]);
    }

    public function exists(int $gameId, int $telegramUserId): bool
    {
        return $this->db->has('game_players', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);
    }

    public function findByGamePlayer(int $gameId, int $telegramUserId): ?array
    {
        return $this->db->get('game_players', '*', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]) ?: null;
    }

    public function findVolleyballCount(int $gameId, int $telegramUserId): ?int
    {
        $value = $this->db->get('game_players', 'volleyball', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);

        return false === $value || null === $value ? null : (int) $value;
    }

    public function findNetCount(int $gameId, int $telegramUserId): ?int
    {
        $value = $this->db->get('game_players', 'net', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);

        return false === $value || null === $value ? null : (int) $value;
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

    public function incrementVolleyball(int $gameId, int $telegramUserId): bool
    {
        $result = $this->db->update('game_players', ['volleyball[+]' => 1], [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);

        return 0 < $result->rowCount();
    }

    public function decrementVolleyball(int $gameId, int $telegramUserId): bool
    {
        $statement = $this->db->pdo->prepare(
            'UPDATE game_players SET volleyball = MAX(0, volleyball - 1) WHERE game_id = :game_id AND telegram_user_id = :telegram_user_id'
        );
        $statement->execute([':game_id' => $gameId, ':telegram_user_id' => $telegramUserId]);

        return 0 < $statement->rowCount();
    }

    public function incrementNet(int $gameId, int $telegramUserId): bool
    {
        $result = $this->db->update('game_players', ['net[+]' => 1], [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);

        return 0 < $result->rowCount();
    }

    public function decrementNet(int $gameId, int $telegramUserId): bool
    {
        $statement = $this->db->pdo->prepare(
            'UPDATE game_players SET net = MAX(0, net - 1) WHERE game_id = :game_id AND telegram_user_id = :telegram_user_id'
        );
        $statement->execute([':game_id' => $gameId, ':telegram_user_id' => $telegramUserId]);

        return 0 < $statement->rowCount();
    }

    public function findEarliestTimeWithNet(int $gameId): ?string
    {
        $statement = $this->db->pdo->prepare(
            'SELECT MIN(time) FROM game_players WHERE game_id = :game_id AND net > 0 AND time IS NOT NULL'
        );
        $statement->execute([':game_id' => $gameId]);

        return $statement->fetchColumn() ?: null;
    }

    public function findEarliestTime(int $gameId): ?string
    {
        $statement = $this->db->pdo->prepare(
            'SELECT MIN(time) FROM game_players WHERE game_id = :game_id AND time IS NOT NULL'
        );
        $statement->execute([':game_id' => $gameId]);

        return $statement->fetchColumn() ?: null;
    }

    public function updateTime(int $gameId, int $telegramUserId, string $time): bool
    {
        $result = $this->db->update('game_players', ['time' => $time], [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);

        return 0 < $result->rowCount();
    }
}
