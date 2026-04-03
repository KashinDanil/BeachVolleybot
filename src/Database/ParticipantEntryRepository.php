<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

readonly class ParticipantEntryRepository extends AbstractRepository
{
    protected function table(): string
    {
        return 'participant_entries';
    }

    public function create(int $gameId, int $participantId, int $position, int $plusOneNumber = 0): int
    {
        $this->db->insert($this->table(), [
            'game_id' => $gameId,
            'participant_id' => $participantId,
            'position' => $position,
            'plus_one_number' => $plusOneNumber,
        ]);

        return (int) $this->db->id();
    }

    public function findByGameId(int $gameId): array
    {
        return $this->db->select($this->table(), '*', [
            'game_id' => $gameId,
            'ORDER' => ['position' => 'ASC'],
        ]);
    }

    public function findByParticipantId(int $participantId): array
    {
        return $this->db->select($this->table(), '*', ['participant_id' => $participantId]);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update($this->table(), $data, ['id' => $id]);
    }

    public function deleteByParticipantId(int $participantId): int
    {
        return $this->db->delete($this->table(), ['participant_id' => $participantId])->rowCount();
    }

    public function getNextPosition(int $gameId): int
    {
        $max = $this->db->max($this->table(), 'position', ['game_id' => $gameId]);

        return null === $max ? 1 : (int) $max + 1;
    }
}