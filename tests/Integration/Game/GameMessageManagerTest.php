<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\GameMessageManager;
use BeachVolleybot\Telegram\Messages\MessageAddress;
use BeachVolleybot\Tests\Fixtures\CreatesGameMessageRecords;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;

final class GameMessageManagerTest extends DatabaseTestCase
{
    use CreatesGameMessageRecords;

    private GameMessageManager $gameMessageManager;

    public function testAddInlineMessageAttachesToJunctionTable(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_1');
        $this->gameMessageManager->addInlineMessage($gameId, 'msg_2', 'query_2');

        $gameMessages = $this->gameMessageManager->findGameMessageRecordsByGameId($gameId);

        $this->assertEquals(
            [MessageAddress::inline('msg_1'), MessageAddress::inline('msg_2')],
            $this->messageAddresses($gameMessages),
        );
        $this->assertSame([null, 'query_2'], array_column($gameMessages, 'inlineQueryId'));
    }

    public function testAddChatMessageAttachesToJunctionTable(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_1');
        $this->gameMessageManager->addChatMessage($gameId, -100, 77);

        $this->assertEquals(
            [MessageAddress::inline('msg_1'), MessageAddress::chat(-100, 77)],
            $this->messageAddresses($this->gameMessageManager->findGameMessageRecordsByGameId($gameId)),
        );
    }

    public function testFindGameMessageRecordsHydratesTheWholeRow(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_1');
        $this->gameMessageManager->addChatMessage($gameId, -100, 77);

        $chatMessage = $this->gameMessageManager->findGameMessageRecordsByGameId($gameId)[1];

        $this->assertSame($gameId, $chatMessage->gameId);
        $this->assertSame(-100, $chatMessage->chatId);
        $this->assertSame(77, $chatMessage->messageId);
        $this->assertNull($chatMessage->inlineMessageId);
        $this->assertNull($chatMessage->inlineQueryId);
    }

    public function testResolveGameIdByMessageAddressFindsAnInlineMessage(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_1');

        $this->assertSame(
            $gameId,
            $this->gameMessageManager->resolveGameIdByMessageAddress(MessageAddress::inline('msg_1')),
        );
    }

    public function testResolveGameIdByMessageAddressFindsAChatMessage(): void
    {
        $gameId = $this->createGame();
        $this->gameMessageManager->addChatMessage($gameId, -100, 77);

        $this->assertSame(
            $gameId,
            $this->gameMessageManager->resolveGameIdByMessageAddress(MessageAddress::chat(-100, 77)),
        );
    }

    public function testResolveGameIdByMessageAddressReturnsNullForAnUnknownMessage(): void
    {
        $this->assertNull(
            $this->gameMessageManager->resolveGameIdByMessageAddress(MessageAddress::chat(-100, 404)),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        $this->gameMessageManager = new GameMessageManager();
    }

    protected function tearDown(): void
    {
        Connection::close();
    }
}
