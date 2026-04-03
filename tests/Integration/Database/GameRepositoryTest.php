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
        $id = $this->repository->create('Friday Game', 100, 'msg_1');

        $this->assertSame(1, $id);
    }

    public function testCreateWithChatAndMessageId(): void
    {
        $id = $this->repository->create('Friday Game', 100, null, 111, 222);

        $game = $this->repository->findById($id);

        $this->assertNull($game['inline_message_id']);
        $this->assertSame(111, $game['chat_id']);
        $this->assertSame(222, $game['message_id']);
    }

    public function testFindByIdReturnsGame(): void
    {
        $id = $this->repository->create('Friday Game', 100, 'msg_1');

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
        $this->repository->create('Saturday Game', 100, 'msg_42');

        $game = $this->repository->findByInlineMessageId('msg_42');

        $this->assertSame('Saturday Game', $game['title']);
    }

    public function testFindByInlineMessageIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findByInlineMessageId('nonexistent'));
    }

    public function testFindByChatAndMessageId(): void
    {
        $this->repository->create('Chat Game', 100, null, 111, 222);

        $game = $this->repository->findByChatAndMessageId(111, 222);

        $this->assertSame('Chat Game', $game['title']);
    }

    public function testFindByChatAndMessageIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findByChatAndMessageId(111, 222));
    }

    public function testCreateFailsWithoutAnyIdentifier(): void
    {
        $this->expectException(\PDOException::class);

        $this->repository->create('Bad Game', 100);
    }

    public function testFindByCreatedByReturnsAllGames(): void
    {
        $this->repository->create('Game 1', 100, 'msg_1');
        $this->repository->create('Game 2', 100, 'msg_2');
        $this->repository->create('Game 3', 999, 'msg_3');

        $games = $this->repository->findByCreatedBy(100);

        $this->assertCount(2, $games);
    }

    public function testFindByCreatedByReturnsEmptyArrayWhenNone(): void
    {
        $this->assertSame([], $this->repository->findByCreatedBy(100));
    }

    public function testDeleteRemovesGame(): void
    {
        $id = $this->repository->create('Friday Game', 100, 'msg_1');

        $this->assertTrue($this->repository->delete($id));
        $this->assertNull($this->repository->findById($id));
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repository->delete(999));
    }
}