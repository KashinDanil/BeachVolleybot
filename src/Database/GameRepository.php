<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

readonly class GameRepository extends AbstractRepository
{
    protected function table(): string
    {
        return 'games';
    }

    public function create(
        string $title,
        int $createdBy,
        ?string $inlineMessageId = null,
        ?int $chatId = null,
        ?int $messageId = null,
    ): int {
        $data = [
            'title' => $title,
            'created_by' => $createdBy,
        ];

        if (null !== $inlineMessageId) {
            $data['inline_message_id'] = $inlineMessageId;
        }
        if (null !== $chatId) {
            $data['chat_id'] = $chatId;
        }
        if (null !== $messageId) {
            $data['message_id'] = $messageId;
        }

        $this->db->insert($this->table(), $data);

        return (int) $this->db->id();
    }

    public function findByInlineMessageId(string $inlineMessageId): ?array
    {
        return $this->db->get($this->table(), '*', ['inline_message_id' => $inlineMessageId]) ?: null;
    }

    public function findByChatAndMessageId(int $chatId, int $messageId): ?array
    {
        return $this->db->get($this->table(), '*', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]) ?: null;
    }

    public function findByCreatedBy(int $createdBy): array
    {
        return $this->db->select($this->table(), '*', ['created_by' => $createdBy]);
    }
}