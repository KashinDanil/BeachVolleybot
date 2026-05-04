<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\GameInlineMessageRepository;
use PDOException;

final class GameInlineMessageRepositoryTest extends DatabaseTestCase
{
    private GameInlineMessageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GameInlineMessageRepository($this->db);
    }

    public function testCreateAndListByGameId(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_first', inlineQueryId: 'query_1');
        $this->repository->create($gameId, 'msg_second');

        $this->assertSame(['msg_first', 'msg_second'], $this->repository->findInlineMessageIdsByGameId($gameId));
    }

    public function testFindReturnsEmptyForUnknownGame(): void
    {
        $this->assertSame([], $this->repository->findInlineMessageIdsByGameId(999));
    }

    public function testDuplicatePairIsRejectedByPrimaryKey(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_x', inlineQueryId: 'query_1');

        $this->expectException(PDOException::class);
        $this->repository->create($gameId, 'msg_x');
    }

    public function testForeignKeyCascadeDeletesJunctionRowsWhenGameIsDeleted(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_a', inlineQueryId: 'query_1');
        $this->repository->create($gameId, 'msg_b');

        $this->db->delete('games', ['game_id' => $gameId]);

        $this->assertSame([], $this->repository->findInlineMessageIdsByGameId($gameId));
    }
}
