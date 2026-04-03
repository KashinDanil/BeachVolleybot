<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

readonly class ParticipantRepository extends AbstractRepository
{
    protected function table(): string
    {
        return 'participants';
    }

    public function upsert(
        int $gameId,
        int $telegramId,
        string $firstName,
        ?string $lastName = null,
        ?string $username = null,
    ): int {
        $this->db->pdo->prepare(
            'INSERT INTO participants (game_id, telegram_id, first_name, last_name, username)
             VALUES (:game_id, :telegram_id, :first_name, :last_name, :username)
             ON CONFLICT (game_id, telegram_id) DO UPDATE SET
                first_name = excluded.first_name,
                last_name = excluded.last_name,
                username = excluded.username'
        )->execute([
            ':game_id' => $gameId,
            ':telegram_id' => $telegramId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':username' => $username,
        ]);

        return (int) $this->db->pdo->lastInsertId();
    }

    public function findByGameAndTelegramId(int $gameId, int $telegramId): ?array
    {
        return $this->db->get($this->table(), '*', [
            'game_id' => $gameId,
            'telegram_id' => $telegramId,
        ]) ?: null;
    }

    public function findByGameId(int $gameId): array
    {
        return $this->db->select($this->table(), '*', [
            'game_id' => $gameId,
            'ORDER' => ['id' => 'ASC'],
        ]);
    }

    public function incrementBall(int $id): void
    {
        $this->increment('ball', $id);
    }

    public function decrementBall(int $id): void
    {
        $this->decrement('ball', $id);
    }

    public function incrementNet(int $id): void
    {
        $this->increment('net', $id);
    }

    public function decrementNet(int $id): void
    {
        $this->decrement('net', $id);
    }

    private function increment(string $column, int $id): void
    {
        $this->db->update($this->table(), ["{$column}[+]" => 1], ['id' => $id]);
    }

    private function decrement(string $column, int $id): void
    {
        $this->db->pdo->prepare(
            "UPDATE participants SET {$column} = MAX(0, {$column} - 1) WHERE id = :id"
        )->execute([':id' => $id]);
    }
}