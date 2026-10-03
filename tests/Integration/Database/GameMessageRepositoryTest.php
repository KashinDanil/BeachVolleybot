<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\GameMessageRepository;
use PDOException;

final class GameMessageRepositoryTest extends DatabaseTestCase
{
    private GameMessageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GameMessageRepository($this->db);
    }

    public function testAddAndListInlineMessagesByGameId(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_first', gameKey: 'query_1');
        $this->repository->addInlineMessage($gameId, 'msg_second', 'query_second');

        $this->assertEquals(
            [
                $this->inlineMessageRow($gameId, 'msg_first'),
                $this->inlineMessageRow($gameId, 'msg_second', 'query_second'),
            ],
            $this->withoutCreatedAt($this->repository->findByGameId($gameId)),
        );
    }

    public function testListReturnsMessagesInCreatedOrder(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_first', gameKey: 'query_1');
        $this->repository->addChatMessage($gameId, -100, 55);

        $this->assertEquals(
            [
                $this->inlineMessageRow($gameId, 'msg_first'),
                ['game_id' => $gameId, 'chat_id' => -100, 'message_id' => 55, 'inline_message_id' => null, 'inline_query_id' => null],
            ],
            $this->withoutCreatedAt($this->repository->findByGameId($gameId)),
        );
    }

    public function testFindReturnsEmptyForUnknownGame(): void
    {
        $this->assertSame([], $this->repository->findByGameId(999));
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

    public function testFindGameIdByInlineQueryIdReturnsId(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_one', gameKey: 'query_1');
        $this->repository->addInlineMessage($gameId, 'msg_two', 'iq_7');

        $this->assertSame($gameId, $this->repository->findGameIdByInlineQueryId('iq_7'));
    }

    public function testFindGameIdByInlineQueryIdReturnsNullWhenUnknown(): void
    {
        $this->assertNull($this->repository->findGameIdByInlineQueryId('nonexistent'));
    }

    public function testDuplicateInlineMessageIsRejected(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_x', gameKey: 'query_1');

        $this->expectException(PDOException::class);
        $this->repository->addInlineMessage($gameId, 'msg_x', 'query_dup');
    }

    public function testDuplicateInlineQueryIdIsRejected(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_a', gameKey: 'query_1');
        $this->repository->addInlineMessage($gameId, 'msg_b', 'query_shared');

        $this->expectException(PDOException::class);
        $this->repository->addInlineMessage($gameId, 'msg_c', 'query_shared');
    }

    public function testRowWithoutIdentityIsRejected(): void
    {
        $gameId = $this->createGame(gameKey: 'query_1');

        $this->expectException(PDOException::class);
        $this->db->insert('game_messages', ['game_id' => $gameId]);
    }

    public function testForeignKeyCascadeDeletesRowsWhenGameIsDeleted(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_a', gameKey: 'query_1');
        $this->repository->addInlineMessage($gameId, 'msg_b', 'query_b');

        $this->db->delete('games', ['game_id' => $gameId]);

        $this->assertSame([], $this->repository->findByGameId($gameId));
    }

    /** @return array<string, mixed> */
    private function inlineMessageRow(int $gameId, string $inlineMessageId, ?string $inlineQueryId = null): array
    {
        return [
            'game_id' => $gameId,
            'chat_id' => null,
            'message_id' => null,
            'inline_message_id' => $inlineMessageId,
            'inline_query_id' => $inlineQueryId,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function withoutCreatedAt(array $rows): array
    {
        return array_map(static fn(array $row): array => array_diff_key($row, ['created_at' => true]), $rows);
    }
}
