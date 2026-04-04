<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\PlayerRepository;

final class PlayerRepositoryTest extends DatabaseTestCase
{
    private PlayerRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PlayerRepository($this->db);
    }

    public function testUpsertInsertsNewPlayer(): void
    {
        $this->repository->upsert(200, 'Danil', 'Kashin', 'danil_kashin');

        $player = $this->repository->findById(200);

        $this->assertSame('Danil', $player['first_name']);
        $this->assertSame('Kashin', $player['last_name']);
        $this->assertSame('danil_kashin', $player['username']);
    }

    public function testUpsertUpdatesExistingPlayer(): void
    {
        $this->repository->upsert(200, 'Danil', 'Kashin', 'old_username');
        $this->repository->upsert(200, 'Danil', 'Kashin', 'new_username');

        $player = $this->repository->findById(200);

        $this->assertSame('new_username', $player['username']);
    }

    public function testUpsertDoesNotCreateDuplicate(): void
    {
        $this->repository->upsert(200, 'Danil');
        $this->repository->upsert(200, 'Danil');

        $this->assertCount(1, $this->repository->findAll());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findById(999));
    }

    public function testDeleteRemovesPlayer(): void
    {
        $this->repository->upsert(200, 'Danil');

        $this->assertTrue($this->repository->delete(200));
        $this->assertNull($this->repository->findById(200));
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repository->delete(999));
    }
}