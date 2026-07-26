<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\GameMessageRepository;
use BeachVolleybot\Telegram\Messages\Targets\ChatGameMessageTarget;
use BeachVolleybot\Telegram\Messages\Targets\InlineGameMessageTarget;
use PDOException;

final class GameMessageRepositoryTest extends DatabaseTestCase
{
    private GameMessageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GameMessageRepository($this->db);
    }

    public function testAddAndListInlineTargetsByGameId(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_first', gameKey: 'query_1');
        $this->repository->addInlineMessage($gameId, 'msg_second');

        $this->assertEquals(
            [new InlineGameMessageTarget('msg_first'), new InlineGameMessageTarget('msg_second')],
            $this->repository->findTargetsByGameId($gameId),
        );
    }

    public function testListReturnsChatTargetsBeforeInlineTargets(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_first', gameKey: 'query_1');
        $this->repository->addChatMessage($gameId, -100, 55);

        $this->assertEquals(
            [new ChatGameMessageTarget(-100, 55), new InlineGameMessageTarget('msg_first')],
            $this->repository->findTargetsByGameId($gameId),
        );
    }

    public function testFindReturnsEmptyForUnknownGame(): void
    {
        $this->assertSame([], $this->repository->findTargetsByGameId(999));
    }

    public function testFindGameIdByInlineMessageIdReturnsId(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_one', gameKey: 'query_1');

        $this->assertSame($gameId, $this->repository->findGameIdByInlineMessageId('msg_one'));
    }

    public function testFindGameIdByInlineMessageIdReturnsNullWhenUnknown(): void
    {
        $this->assertNull($this->repository->findGameIdByInlineMessageId('nonexistent'));
    }

    public function testFindGameIdByChatMessageReturnsId(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_one', gameKey: 'query_1');
        $this->repository->addChatMessage($gameId, -4242, 7);

        $this->assertSame($gameId, $this->repository->findGameIdByChatMessage(-4242, 7));
    }

    public function testFindGameIdByChatMessageReturnsNullWhenUnknown(): void
    {
        $this->assertNull($this->repository->findGameIdByChatMessage(-1, 1));
    }

    public function testDuplicateInlineMessageIsRejected(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_x', gameKey: 'query_1');

        $this->expectException(PDOException::class);
        $this->repository->addInlineMessage($gameId, 'msg_x');
    }

    public function testForeignKeyCascadeDeletesRowsWhenGameIsDeleted(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_a', gameKey: 'query_1');
        $this->repository->addInlineMessage($gameId, 'msg_b');

        $this->db->delete('games', ['game_id' => $gameId]);

        $this->assertSame([], $this->repository->findTargetsByGameId($gameId));
    }
}
