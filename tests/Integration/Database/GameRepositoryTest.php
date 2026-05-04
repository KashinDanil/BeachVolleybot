<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\GameRepository;

final class GameRepositoryTest extends DatabaseTestCase
{
    private GameRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GameRepository($this->db);
    }

    public function testCreateReturnsId(): void
    {
        $id = $this->repository->create('Friday Game', 100, 'query_1');

        $this->assertSame(1, $id);
    }

    public function testFindByIdReturnsGame(): void
    {
        $id = $this->repository->create('Friday Game', 100, 'query_1');

        $game = $this->repository->findById($id);

        $this->assertSame('query_1', $game['inline_query_id']);
        $this->assertSame('Friday Game', $game['title']);
        $this->assertSame(100, $game['created_by']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findById(999));
    }

    public function testFindByInlineQueryId(): void
    {
        $this->repository->create('Saturday Game', 100, 'query_42');

        $game = $this->repository->findByInlineQueryId('query_42');

        $this->assertSame('Saturday Game', $game['title']);
    }

    public function testFindByInlineQueryIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findByInlineQueryId('nonexistent'));
    }

    public function testFindGameIdByInlineQueryIdReturnsId(): void
    {
        $id = $this->repository->create('Friday Game', 100, 'query_77');

        $this->assertSame($id, $this->repository->findGameIdByInlineQueryId('query_77'));
    }

    public function testFindGameIdByInlineQueryIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findGameIdByInlineQueryId('nonexistent'));
    }

    public function testDeleteRemovesGame(): void
    {
        $id = $this->repository->create('Friday Game', 100, 'query_1');

        $this->assertTrue($this->repository->delete($id));
        $this->assertNull($this->repository->findById($id));
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repository->delete(999));
    }
}
