<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

readonly class PlayerRepository extends AbstractRepository
{
    protected function table(): string
    {
        return 'players';
    }

    protected function primaryKeyColumn(): string
    {
        return 'telegram_user_id';
    }

    public function upsert(
        int $telegramUserId,
        string $firstName,
        ?string $lastName = null,
        ?string $username = null,
    ): void {
        $this->db->pdo->prepare(
            'INSERT INTO players (telegram_user_id, first_name, last_name, username)
             VALUES (:telegram_user_id, :first_name, :last_name, :username)
             ON CONFLICT (telegram_user_id) DO UPDATE SET
                first_name = excluded.first_name,
                last_name = excluded.last_name,
                username = excluded.username,
                updated_at = CURRENT_TIMESTAMP'
        )->execute([
            ':telegram_user_id' => $telegramUserId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':username' => $username,
        ]);
    }

    public function findAll(): array
    {
        return $this->db->select($this->table(), '*');
    }
}