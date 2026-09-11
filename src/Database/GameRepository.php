<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\ParsedTitle;
use DateTimeImmutable;

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

    public function create(
        string $title,
        int $createdBy,
        string $gameKey,
        ParsedTitle $parsedTitle,
        ?string $location = null,
        GameSettings $settings = new GameSettings(),
    ): int {
        $this->db->insert($this->table(), [
            'title' => $title,
            'location' => $location,
            'created_by' => $createdBy,
            'game_key' => $gameKey,
            'kickoff_at' => Timestamp::format($parsedTitle->kickoffAt),
            'venue_name' => $parsedTitle->venueName,
            'settings_json' => json_encode($settings, JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->db->id();
    }

    public function updateLocation(int $gameId, ?string $location): void
    {
        $this->db->update($this->table(), ['location' => $location], ['game_id' => $gameId]);
    }

    public function updateSettings(int $gameId, GameSettings $settings): void
    {
        $this->db->update(
            $this->table(),
            ['settings_json' => json_encode($settings, JSON_THROW_ON_ERROR)],
            ['game_id' => $gameId],
        );
    }

    public function updateTitle(int $gameId, string $title, ParsedTitle $parsedTitle): void
    {
        $this->db->update($this->table(), [
            'title' => $title,
            'kickoff_at' => Timestamp::format($parsedTitle->kickoffAt),
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
            'kickoff_at[<>]' => [Timestamp::format($from), Timestamp::format($to)],
            'ORDER' => ['kickoff_at' => 'ASC'],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByKickoffBetween(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return $this->db->select($this->table(), '*', [
            'kickoff_at[>=]' => Timestamp::format($from),
            'kickoff_at[<]' => Timestamp::format($until),
            'ORDER' => ['kickoff_at' => 'ASC'],
        ]);
    }
}
