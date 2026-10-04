<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use PDO;

/** @internal Use \BeachVolleybot\User\UserManager instead; it is the only intended caller. */
readonly class UserRepository extends AbstractRepository
{
    protected function table(): string
    {
        return 'users';
    }

    protected function primaryKeyColumn(): string
    {
        return 'telegram_user_id';
    }

    /**
     * @return array<string, mixed> The stored row
     */
    public function upsert(
        int $telegramUserId,
        string $firstName,
        ?string $lastName = null,
        ?string $username = null,
        ?string $languageCode = null,
        ?int $initialNotifications = null,
    ): array {
        $rows = $this->db->query(
            'INSERT INTO users (telegram_user_id, first_name, last_name, username, language_code, notifications)
             VALUES (:telegram_user_id, :first_name, :last_name, :username, :language_code, :notifications)
             ON CONFLICT (telegram_user_id) DO UPDATE SET
                first_name = excluded.first_name,
                last_name = excluded.last_name,
                username = excluded.username,
                language_code = COALESCE(excluded.language_code, users.language_code),
                notifications = COALESCE(users.notifications, excluded.notifications),
                updated_at = CURRENT_TIMESTAMP
             RETURNING *',
            [
                ':telegram_user_id' => $telegramUserId,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':username' => $username,
                ':language_code' => $languageCode,
                ':notifications' => $initialNotifications,
            ],
        )->fetchAll(PDO::FETCH_ASSOC);

        return $rows[0];
    }

    /** @return list<array<string, mixed>> */
    public function findAllPaginated(int $limit, int $offset): array
    {
        return $this->db->select($this->table(), '*', [
            'ORDER' => ['role' => 'DESC', 'first_name' => 'ASC'],
            'LIMIT' => [$offset, $limit],
        ]);
    }

    public function countAll(): int
    {
        return $this->db->count($this->table());
    }

    public function updateRole(int $telegramUserId, int $role): void
    {
        $this->db->update($this->table(), ['role' => $role], [$this->primaryKeyColumn() => $telegramUserId]);
    }

    public function updateNotifications(int $telegramUserId, int $notifications): void
    {
        $this->db->update(
            $this->table(),
            ['notifications' => $notifications],
            [$this->primaryKeyColumn() => $telegramUserId],
        );
    }
}
