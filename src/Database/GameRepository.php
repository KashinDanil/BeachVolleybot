<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

readonly class GameRepository extends AbstractRepository
{
    protected function table(): string
    {
        return 'games';
    }

    public function create(string $inlineMessageId, string $title, int $createdBy): int
    {
        $this->db->insert($this->table(), [
            'inline_message_id' => $inlineMessageId,
            'title' => $title,
            'created_by' => $createdBy,
        ]);

        return (int) $this->db->id();
    }

    public function findByInlineMessageId(string $inlineMessageId): ?array
    {
        return $this->db->get($this->table(), '*', ['inline_message_id' => $inlineMessageId]) ?: null;
    }

    public function findByCreatedBy(int $createdBy): array
    {
        return $this->db->select($this->table(), '*', ['created_by' => $createdBy]);
    }
}