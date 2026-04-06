<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\GameSlotRepository;

final class GameSlotRepositoryTest extends DatabaseTestCase
{
    private GameSlotRepository $repository;
    private int $gameId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GameSlotRepository($this->db);
        $this->gameId = $this->createGame();
        $this->createGamePlayer($this->gameId, 200);
    }

    public function testCreateAndFindByGameId(): void
    {
        $this->repository->create($this->gameId, 200, 1);

        $slots = $this->repository->findByGameId($this->gameId);

        $this->assertCount(1, $slots);
        $this->assertSame(1, $slots[0]['position']);
        $this->assertSame(200, $slots[0]['telegram_user_id']);
    }

    public function testFindByGameIdReturnsOrderedByPosition(): void
    {
        $this->createGamePlayer($this->gameId, 201);
        $this->repository->create($this->gameId, 200, 3);
        $this->repository->create($this->gameId, 201, 1);

        $slots = $this->repository->findByGameId($this->gameId);

        $this->assertCount(2, $slots);
        $this->assertSame(1, $slots[0]['position']);
        $this->assertSame(3, $slots[1]['position']);
    }

    public function testFindByGameIdReturnsEmptyArrayWhenNone(): void
    {
        $this->assertSame([], $this->repository->findByGameId($this->gameId));
    }

    public function testFindByPlayer(): void
    {
        $this->repository->create($this->gameId, 200, 1);
        $this->repository->create($this->gameId, 200, 2);

        $slots = $this->repository->findByPlayer($this->gameId, 200);

        $this->assertCount(2, $slots);
    }

    public function testDeleteRemovesSlot(): void
    {
        $this->repository->create($this->gameId, 200, 1);

        $this->assertTrue($this->repository->delete($this->gameId, 1));
        $this->assertSame([], $this->repository->findByGameId($this->gameId));
    }

    public function testDeleteReordersPositionsAboveDeleted(): void
    {
        $this->createGamePlayer($this->gameId, 201);
        $this->repository->create($this->gameId, 200, 1);
        $this->repository->create($this->gameId, 201, 2);

        $this->repository->delete($this->gameId, 1);

        $slots = $this->repository->findByGameId($this->gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, $slots[0]['position']);
        $this->assertSame(201, $slots[0]['telegram_user_id']);
    }

    public function testDeleteReordersMultiplePositionsAboveDeleted(): void
    {
        $this->createGamePlayer($this->gameId, 201);
        $this->createGamePlayer($this->gameId, 202);
        $this->repository->create($this->gameId, 200, 1);
        $this->repository->create($this->gameId, 201, 2);
        $this->repository->create($this->gameId, 202, 3);

        $this->repository->delete($this->gameId, 1);

        $slots = $this->repository->findByGameId($this->gameId);
        $this->assertCount(2, $slots);
        $this->assertSame(1, $slots[0]['position']);
        $this->assertSame(2, $slots[1]['position']);
    }

    public function testDeleteLastPositionDoesNotAffectOthers(): void
    {
        $this->createGamePlayer($this->gameId, 201);
        $this->repository->create($this->gameId, 200, 1);
        $this->repository->create($this->gameId, 201, 2);

        $this->repository->delete($this->gameId, 2);

        $slots = $this->repository->findByGameId($this->gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, $slots[0]['position']);
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repository->delete($this->gameId, 999));
    }

    public function testDeleteByPlayerRemovesAllSlots(): void
    {
        $this->repository->create($this->gameId, 200, 1);
        $this->repository->create($this->gameId, 200, 2);

        $deleted = $this->repository->deleteByPlayer($this->gameId, 200);

        $this->assertSame(2, $deleted);
        $this->assertSame([], $this->repository->findByPlayer($this->gameId, 200));
    }

    public function testDeleteByPlayerReturnsZeroWhenNone(): void
    {
        $this->assertSame(0, $this->repository->deleteByPlayer($this->gameId, 200));
    }

    public function testGetNextPositionReturnsOneForEmptyGame(): void
    {
        $this->assertSame(1, $this->repository->getNextPosition($this->gameId));
    }

    public function testGetNextPositionReturnsMaxPlusOne(): void
    {
        $this->repository->create($this->gameId, 200, 3);

        $this->assertSame(4, $this->repository->getNextPosition($this->gameId));
    }

    public function testCascadeDeleteOnGamePlayerRemoval(): void
    {
        $this->repository->create($this->gameId, 200, 1);
        $this->repository->create($this->gameId, 200, 2);

        $this->db->delete('game_players', [
            'game_id' => $this->gameId,
            'telegram_user_id' => 200,
        ]);

        $this->assertSame([], $this->repository->findByGameId($this->gameId));
    }
}