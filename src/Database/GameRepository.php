<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

readonly class GameRepository extends AbstractRepository
{
    protected function table(): string
    {
        return 'games';
    }

    protected function primaryKeyColumn(): string
    {
        return 'game_id';
    }

    public function create(string $title, int $createdBy, string $inlineMessageId, string $inlineQueryId, ?string $location = null): int
    {
        $this->db->insert($this->table(), [
            'title' => $title,
            'location' => $location,
            'created_by' => $createdBy,
            'inline_message_id' => $inlineMessageId,
            'inline_query_id' => $inlineQueryId,
        ]);

        return (int) $this->db->id();
    }

    public function updateLocation(int $gameId, ?string $location): void
    {
        $this->db->update($this->table(), ['location' => $location], ['game_id' => $gameId]);
    }

    public function updateTitle(int $gameId, string $title): void
    {
        $this->db->update($this->table(), ['title' => $title], ['game_id' => $gameId]);
    }

    public function findTitleByGameId(int $gameId): ?string
    {
        return $this->db->get($this->table(), 'title', ['game_id' => $gameId]) ?: null;
    }

    public function findInlineMessageIdByGameId(int $gameId): ?string
    {
        return $this->db->get($this->table(), 'inline_message_id', ['game_id' => $gameId]) ?: null;
    }

    public function findByInlineMessageId(string $inlineMessageId): ?array
    {
        return $this->db->get($this->table(), '*', ['inline_message_id' => $inlineMessageId]) ?: null;
    }

    public function findGameIdByInlineMessageId(string $inlineMessageId): ?int
    {
        $gameId = $this->db->get($this->table(), 'game_id', ['inline_message_id' => $inlineMessageId]);

        return $gameId ? (int) $gameId : null;
    }

    public function findByInlineQueryId(string $inlineQueryId): ?array
    {
        return $this->db->get($this->table(), '*', ['inline_query_id' => $inlineQueryId]) ?: null;
    }

    public function findInlineMessageIdByInlineQueryId(string $inlineQueryId): ?string
    {
        return $this->db->get($this->table(), 'inline_message_id', ['inline_query_id' => $inlineQueryId]) ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function findAllDescending(int $limit, int $offset): array
    {
        return $this->db->select($this->table(), '*', [
            'ORDER' => ['game_id' => 'DESC'],
            'LIMIT' => [$offset, $limit],
        ]);
    }

    public function countAll(): int
    {
        return $this->db->count($this->table());
    }
}
