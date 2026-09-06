<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use BeachVolleybot\Game\ParsedTitle;
use DateTimeImmutable;

readonly class GameRepository extends AbstractRepository
{
    private const string TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    protected function table(): string
    {
        return 'games';
    }

    protected function primaryKeyColumn(): string
    {
        return 'game_id';
    }

    public function create(
        string $title,
        int $createdBy,
        string $gameKey,
        ParsedTitle $parsedTitle,
        ?string $location = null,
    ): int {
        $this->db->insert($this->table(), [
            'title' => $title,
            'location' => $location,
            'created_by' => $createdBy,
            'game_key' => $gameKey,
            'kickoff_at' => $parsedTitle->kickoffAt->format(self::TIMESTAMP_FORMAT),
            'venue_name' => $parsedTitle->venueName,
        ]);

        return (int) $this->db->id();
    }

    public function updateLocation(int $gameId, ?string $location): void
    {
        $this->db->update($this->table(), ['location' => $location], ['game_id' => $gameId]);
    }

    public function updateTitle(int $gameId, string $title, ParsedTitle $parsedTitle): void
    {
        $this->db->update($this->table(), [
            'title' => $title,
            'kickoff_at' => $parsedTitle->kickoffAt->format(self::TIMESTAMP_FORMAT),
            'venue_name' => $parsedTitle->venueName,
        ], ['game_id' => $gameId]);
    }

    public function findTitleByGameId(int $gameId): ?string
    {
        return $this->db->get($this->table(), 'title', ['game_id' => $gameId]) ?: null;
    }

    public function findByGameKey(string $gameKey): ?array
    {
        return $this->db->get($this->table(), '*', ['game_key' => $gameKey]) ?: null;
    }

    public function findGameIdByGameKey(string $gameKey): ?int
    {
        $gameId = $this->db->get($this->table(), 'game_id', ['game_key' => $gameKey]);

        return $gameId ? (int)$gameId : null;
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

    /** @return list<array<string, mixed>> */
    public function findByCreator(int $createdBy, int $limit, int $offset): array
    {
        return $this->db->select($this->table(), '*', [
            'created_by' => $createdBy,
            'ORDER' => ['game_id' => 'DESC'],
            'LIMIT' => [$offset, $limit],
        ]);
    }

    public function countByCreator(int $createdBy): int
    {
        return $this->db->count($this->table(), ['created_by' => $createdBy]);
    }

    /**
     * Games kicking off inside the window, soonest first.
     *
     * @return list<array<string, mixed>>
     */
    public function findUpcoming(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->db->select($this->table(), '*', [
            'kickoff_at[<>]' => [$from->format(self::TIMESTAMP_FORMAT), $to->format(self::TIMESTAMP_FORMAT)],
            'ORDER' => ['kickoff_at' => 'ASC'],
        ]);
    }
}
