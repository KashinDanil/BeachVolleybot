<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\UserRepository;

final class UserRepositoryTest extends DatabaseTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository($this->db);
    }

    public function testUpsertInsertsNewUser(): void
    {
        $this->repository->upsert(200, 'Danil', 'Kashin', 'danil_kashin');

        $user = $this->repository->findById(200);

        $this->assertSame('Danil', $user['first_name']);
        $this->assertSame('Kashin', $user['last_name']);
        $this->assertSame('danil_kashin', $user['username']);
    }

    public function testUpsertUpdatesExistingUser(): void
    {
        $this->repository->upsert(200, 'Danil', 'Kashin', 'old_username');
        $this->repository->upsert(200, 'Danil', 'Kashin', 'new_username');

        $user = $this->repository->findById(200);

        $this->assertSame('new_username', $user['username']);
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

    public function testDeleteRemovesUser(): void
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