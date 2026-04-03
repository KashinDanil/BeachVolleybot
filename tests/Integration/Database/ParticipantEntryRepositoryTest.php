<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\ParticipantEntryRepository;

final class ParticipantEntryRepositoryTest extends DatabaseTestCase
{
    private ParticipantEntryRepository $repository;
    private int $gameId;
    private int $participantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ParticipantEntryRepository($this->db);
        $this->gameId = $this->createGame();
        $this->participantId = $this->createParticipant($this->gameId);
    }

    public function testCreateReturnsId(): void
    {
        $id = $this->repository->create($this->gameId, $this->participantId, 1);

        $this->assertSame(1, $id);
    }

    public function testFindByIdReturnsEntry(): void
    {
        $id = $this->repository->create($this->gameId, $this->participantId, 1, 2);

        $entry = $this->repository->findById($id);

        $this->assertSame($this->gameId, $entry['game_id']);
        $this->assertSame($this->participantId, $entry['participant_id']);
        $this->assertSame(1, $entry['position']);
        $this->assertSame(2, $entry['plus_one_number']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findById(999));
    }

    public function testFindByGameIdReturnsOrderedByPosition(): void
    {
        $participant2 = $this->createParticipant($this->gameId, 201, 'Bob');

        $this->repository->create($this->gameId, $this->participantId, 3);
        $this->repository->create($this->gameId, $participant2, 1);

        $entries = $this->repository->findByGameId($this->gameId);

        $this->assertCount(2, $entries);
        $this->assertSame(1, $entries[0]['position']);
        $this->assertSame(3, $entries[1]['position']);
    }

    public function testFindByGameIdReturnsEmptyArrayWhenNone(): void
    {
        $this->assertSame([], $this->repository->findByGameId($this->gameId));
    }

    public function testFindByParticipantId(): void
    {
        $this->repository->create($this->gameId, $this->participantId, 1);
        $this->repository->create($this->gameId, $this->participantId, 2);

        $entries = $this->repository->findByParticipantId($this->participantId);

        $this->assertCount(2, $entries);
    }

    public function testUpdatePosition(): void
    {
        $id = $this->repository->create($this->gameId, $this->participantId, 1);

        $this->repository->update($id, ['position' => 5]);

        $this->assertSame(5, $this->repository->findById($id)['position']);
    }

    public function testUpdatePlusOneNumber(): void
    {
        $id = $this->repository->create($this->gameId, $this->participantId, 1);

        $this->repository->update($id, ['plus_one_number' => 3]);

        $this->assertSame(3, $this->repository->findById($id)['plus_one_number']);
    }

    public function testDeleteRemovesEntry(): void
    {
        $id = $this->repository->create($this->gameId, $this->participantId, 1);

        $this->assertTrue($this->repository->delete($id));
        $this->assertNull($this->repository->findById($id));
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repository->delete(999));
    }

    public function testDeleteByParticipantIdRemovesAllEntries(): void
    {
        $this->repository->create($this->gameId, $this->participantId, 1);
        $this->repository->create($this->gameId, $this->participantId, 2);

        $deleted = $this->repository->deleteByParticipantId($this->participantId);

        $this->assertSame(2, $deleted);
        $this->assertSame([], $this->repository->findByParticipantId($this->participantId));
    }

    public function testDeleteByParticipantIdReturnsZeroWhenNone(): void
    {
        $this->assertSame(0, $this->repository->deleteByParticipantId($this->participantId));
    }

    public function testGetNextPositionReturnsOneForEmptyGame(): void
    {
        $this->assertSame(1, $this->repository->getNextPosition($this->gameId));
    }

    public function testGetNextPositionReturnsMaxPlusOne(): void
    {
        $this->repository->create($this->gameId, $this->participantId, 3);

        $this->assertSame(4, $this->repository->getNextPosition($this->gameId));
    }
}