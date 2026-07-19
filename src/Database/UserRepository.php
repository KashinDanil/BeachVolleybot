<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use BeachVolleybot\User\Role;

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

    public function upsert(
        int $telegramUserId,
        string $firstName,
        ?string $lastName = null,
        ?string $username = null,
    ): void {
        $this->db->pdo->prepare(
            'INSERT INTO users (telegram_user_id, first_name, last_name, username)
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

    public function findRoleById(int $telegramUserId): ?int
    {
        $role = $this->db->get($this->table(), 'role', [$this->primaryKeyColumn() => $telegramUserId]);

        return null === $role ? null : (int)$role;
    }

    public function findAll(): array
    {
        return $this->db->select($this->table(), '*');
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

    public function updateRole(int $telegramUserId, Role $role): void
    {
        $this->db->update($this->table(), ['role' => $role->value], [$this->primaryKeyColumn() => $telegramUserId]);
    }
}
