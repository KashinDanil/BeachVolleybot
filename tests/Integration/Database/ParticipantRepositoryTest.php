<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\ParticipantRepository;

final class ParticipantRepositoryTest extends DatabaseTestCase
{
    private ParticipantRepository $repository;
    private int $gameId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ParticipantRepository($this->db);
        $this->gameId = $this->createGame();
    }

    public function testUpsertInsertsNewParticipant(): void
    {
        $id = $this->repository->upsert($this->gameId, 200, 'Danil', 'Kashin', 'danil_kashin');

        $participant = $this->repository->findById($id);

        $this->assertSame('Danil', $participant['first_name']);
        $this->assertSame('Kashin', $participant['last_name']);
        $this->assertSame('danil_kashin', $participant['username']);
    }

    public function testUpsertUpdatesExistingParticipant(): void
    {
        $this->repository->upsert($this->gameId, 200, 'Danil', 'Kashin', 'old_username');
        $id = $this->repository->upsert($this->gameId, 200, 'Danil', 'Kashin', 'new_username');

        $participant = $this->repository->findById($id);

        $this->assertSame('new_username', $participant['username']);
    }

    public function testUpsertDoesNotCreateDuplicate(): void
    {
        $this->repository->upsert($this->gameId, 200, 'Danil');
        $this->repository->upsert($this->gameId, 200, 'Danil');

        $this->assertCount(1, $this->repository->findByGameId($this->gameId));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findById(999));
    }

    public function testFindByGameAndTelegramId(): void
    {
        $this->repository->upsert($this->gameId, 200, 'Danil');

        $participant = $this->repository->findByGameAndTelegramId($this->gameId, 200);

        $this->assertSame('Danil', $participant['first_name']);
    }

    public function testFindByGameAndTelegramIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findByGameAndTelegramId($this->gameId, 999));
    }

    public function testFindByGameIdReturnsOrderedList(): void
    {
        $this->repository->upsert($this->gameId, 201, 'Alice');
        $this->repository->upsert($this->gameId, 202, 'Bob');

        $participants = $this->repository->findByGameId($this->gameId);

        $this->assertCount(2, $participants);
        $this->assertSame('Alice', $participants[0]['first_name']);
        $this->assertSame('Bob', $participants[1]['first_name']);
    }

    public function testFindByGameIdReturnsEmptyArrayWhenNone(): void
    {
        $this->assertSame([], $this->repository->findByGameId($this->gameId));
    }

    public function testIncrementBall(): void
    {
        $id = $this->repository->upsert($this->gameId, 200, 'Danil');

        $this->repository->incrementBall($id);
        $this->repository->incrementBall($id);

        $this->assertSame(2, $this->repository->findById($id)['ball']);
    }

    public function testDecrementBallFloorsAtZero(): void
    {
        $id = $this->repository->upsert($this->gameId, 200, 'Danil');

        $this->repository->decrementBall($id);

        $this->assertSame(0, $this->repository->findById($id)['ball']);
    }

    public function testDecrementBallDecrementsFromPositive(): void
    {
        $id = $this->repository->upsert($this->gameId, 200, 'Danil');

        $this->repository->incrementBall($id);
        $this->repository->incrementBall($id);
        $this->repository->decrementBall($id);

        $this->assertSame(1, $this->repository->findById($id)['ball']);
    }

    public function testIncrementNet(): void
    {
        $id = $this->repository->upsert($this->gameId, 200, 'Danil');

        $this->repository->incrementNet($id);

        $this->assertSame(1, $this->repository->findById($id)['net']);
    }

    public function testDecrementNetFloorsAtZero(): void
    {
        $id = $this->repository->upsert($this->gameId, 200, 'Danil');

        $this->repository->decrementNet($id);

        $this->assertSame(0, $this->repository->findById($id)['net']);
    }

    public function testDeleteRemovesParticipant(): void
    {
        $id = $this->repository->upsert($this->gameId, 200, 'Danil');

        $this->assertTrue($this->repository->delete($id));
        $this->assertNull($this->repository->findById($id));
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repository->delete(999));
    }
}