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
        $id = $this->repository->create('msg_1', 'Friday Game', 100);

        $this->assertSame(1, $id);
    }

    public function testFindByIdReturnsGame(): void
    {
        $id = $this->repository->create('msg_1', 'Friday Game', 100);

        $game = $this->repository->findById($id);

        $this->assertSame('msg_1', $game['inline_message_id']);
        $this->assertSame('Friday Game', $game['title']);
        $this->assertSame(100, $game['created_by']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findById(999));
    }

    public function testFindByInlineMessageId(): void
    {
        $this->repository->create('msg_42', 'Saturday Game', 100);

        $game = $this->repository->findByInlineMessageId('msg_42');

        $this->assertSame('Saturday Game', $game['title']);
    }

    public function testFindByInlineMessageIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findByInlineMessageId('nonexistent'));
    }

    public function testFindByCreatedByReturnsAllGames(): void
    {
        $this->repository->create('msg_1', 'Game 1', 100);
        $this->repository->create('msg_2', 'Game 2', 100);
        $this->repository->create('msg_3', 'Game 3', 999);

        $games = $this->repository->findByCreatedBy(100);

        $this->assertCount(2, $games);
    }

    public function testFindByCreatedByReturnsEmptyArrayWhenNone(): void
    {
        $this->assertSame([], $this->repository->findByCreatedBy(100));
    }

    public function testDeleteRemovesGame(): void
    {
        $id = $this->repository->create('msg_1', 'Friday Game', 100);

        $this->assertTrue($this->repository->delete($id));
        $this->assertNull($this->repository->findById($id));
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repository->delete(999));
    }
}