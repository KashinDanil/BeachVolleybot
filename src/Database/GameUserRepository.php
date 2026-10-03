<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use Medoo\Medoo;

readonly class GameUserRepository
{
    public function __construct(
        private Medoo $db,
    ) {
    }

    public function create(int $gameId, int $telegramUserId, string $time, int $volleyball = 0, int $net = 0): void
    {
        $this->db->insert('game_users', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'time' => $time,
            'volleyball' => $volleyball,
            'net' => $net,
        ]);
    }

    public function exists(int $gameId, int $telegramUserId): bool
    {
        return $this->db->has('game_users', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);
    }

    public function findByGameUser(int $gameId, int $telegramUserId): ?array
    {
        return $this->db->get('game_users', '*', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]) ?: null;
    }

    public function findByGameId(int $gameId): array
    {
        return $this->db->select('game_users', '*', ['game_id' => $gameId]);
    }

    /** @return list<int> */
    public function findUserIdsExcept(int $gameId, int $excludedUserId): array
    {
        return $this->db->select('game_users', 'telegram_user_id', [
            'game_id' => $gameId,
            'telegram_user_id[!]' => $excludedUserId,
        ]);
    }

    public function delete(int $gameId, int $telegramUserId): bool
    {
        $result = $this->db->delete('game_users', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);

        return 0 < $result->rowCount();
    }

    public function incrementVolleyball(int $gameId, int $telegramUserId): bool
    {
        $result = $this->db->update('game_users', ['volleyball[+]' => 1], [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);

        return 0 < $result->rowCount();
    }

    public function decrementVolleyball(int $gameId, int $telegramUserId): bool
    {
        $statement = $this->db->query(
            'UPDATE game_users SET volleyball = MAX(0, volleyball - 1) WHERE game_id = :game_id AND telegram_user_id = :telegram_user_id',
            [':game_id' => $gameId, ':telegram_user_id' => $telegramUserId],
        );

        return 0 < $statement->rowCount();
    }

    public function incrementNet(int $gameId, int $telegramUserId): bool
    {
        $result = $this->db->update('game_users', ['net[+]' => 1], [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);

        return 0 < $result->rowCount();
    }

    public function decrementNet(int $gameId, int $telegramUserId): bool
    {
        $statement = $this->db->query(
            'UPDATE game_users SET net = MAX(0, net - 1) WHERE game_id = :game_id AND telegram_user_id = :telegram_user_id',
            [':game_id' => $gameId, ':telegram_user_id' => $telegramUserId],
        );

        return 0 < $statement->rowCount();
    }

    public function findEarliestTimeWithEquipment(int $gameId): ?string
    {
        return $this->db->min('game_users', 'time', [
            'game_id' => $gameId,
            'time[!]' => null,
            'OR' => ['net[>]' => 0, 'volleyball[>]' => 0],
        ]) ?: null;
    }

    public function findEarliestTime(int $gameId): ?string
    {
        return $this->db->min('game_users', 'time', ['game_id' => $gameId, 'time[!]' => null]) ?: null;
    }

    public function updateTime(int $gameId, int $telegramUserId, string $time): bool
    {
        $result = $this->db->update('game_users', ['time' => $time], [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
        ]);

        return 0 < $result->rowCount();
    }
}
