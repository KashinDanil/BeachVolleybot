<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\GamePlayerRepository;

final class GamePlayerRepositoryTest extends DatabaseTestCase
{
    private GamePlayerRepository $repository;
    private int $gameId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GamePlayerRepository($this->db);
        $this->gameId = $this->createGame();
        $this->createPlayer(200, 'Danil');
    }

    public function testCreateAndFind(): void
    {
        $this->repository->create($this->gameId, 200, '15:20');

        $entry = $this->repository->findByGameAndPlayer($this->gameId, 200);

        $this->assertSame($this->gameId, $entry['game_id']);
        $this->assertSame(200, $entry['telegram_user_id']);
        $this->assertSame('15:20', $entry['time']);
    }

    public function testFindByGameAndPlayerReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findByGameAndPlayer($this->gameId, 999));
    }

    public function testFindByGameIdReturnsList(): void
    {
        $this->createPlayer(201, 'Bob');
        $this->repository->create($this->gameId, 200);
        $this->repository->create($this->gameId, 201);

        $entries = $this->repository->findByGameId($this->gameId);

        $this->assertCount(2, $entries);
    }

    public function testFindByGameIdReturnsEmptyArrayWhenNone(): void
    {
        $this->assertSame([], $this->repository->findByGameId($this->gameId));
    }

    public function testIncrementBall(): void
    {
        $this->repository->create($this->gameId, 200);

        $this->repository->incrementBall($this->gameId, 200);
        $this->repository->incrementBall($this->gameId, 200);

        $this->assertSame(2, $this->repository->findByGameAndPlayer($this->gameId, 200)['ball']);
    }

    public function testDecrementBallFloorsAtZero(): void
    {
        $this->repository->create($this->gameId, 200);

        $this->repository->decrementBall($this->gameId, 200);

        $this->assertSame(0, $this->repository->findByGameAndPlayer($this->gameId, 200)['ball']);
    }

    public function testDecrementBallDecrementsFromPositive(): void
    {
        $this->repository->create($this->gameId, 200);

        $this->repository->incrementBall($this->gameId, 200);
        $this->repository->incrementBall($this->gameId, 200);
        $this->repository->decrementBall($this->gameId, 200);

        $this->assertSame(1, $this->repository->findByGameAndPlayer($this->gameId, 200)['ball']);
    }

    public function testIncrementNet(): void
    {
        $this->repository->create($this->gameId, 200);

        $this->repository->incrementNet($this->gameId, 200);

        $this->assertSame(1, $this->repository->findByGameAndPlayer($this->gameId, 200)['net']);
    }

    public function testDecrementNetFloorsAtZero(): void
    {
        $this->repository->create($this->gameId, 200);

        $this->repository->decrementNet($this->gameId, 200);

        $this->assertSame(0, $this->repository->findByGameAndPlayer($this->gameId, 200)['net']);
    }

    public function testDeleteRemovesEntry(): void
    {
        $this->repository->create($this->gameId, 200);

        $this->assertTrue($this->repository->delete($this->gameId, 200));
        $this->assertNull($this->repository->findByGameAndPlayer($this->gameId, 200));
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repository->delete($this->gameId, 999));
    }

    public function testCascadeDeleteOnGameRemoval(): void
    {
        $this->repository->create($this->gameId, 200);

        $this->db->delete('games', ['game_id' => $this->gameId]);

        $this->assertSame([], $this->repository->findByGameId($this->gameId));
    }
}