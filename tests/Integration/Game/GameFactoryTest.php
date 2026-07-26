<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Telegram\Messages\Targets\InlineGameMessageTarget;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use RuntimeException;

final class GameFactoryTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    // --- fromGameId ---

    public function testFromGameIdReturnsGame(): void
    {
        $gameId = $this->createGame(title: 'Sunday Game 18:00', inlineMessageId: 'msg_1');

        $game = GameFactory::fromGameId($gameId);

        $this->assertSame($gameId, $game->getGameId());
        $this->assertEquals([new InlineGameMessageTarget('msg_1')], $game->getMessageTargets());
        $this->assertSame('Sunday Game 18:00', $game->getTitle());
    }

    public function testFromGameIdLoadsAllAttachedInlineMessageIds(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_first');
        $this->attachInlineMessage($gameId, 'msg_second');

        $game = GameFactory::fromGameId($gameId);

        $this->assertEquals(
            [new InlineGameMessageTarget('msg_first'), new InlineGameMessageTarget('msg_second')],
            $game->getMessageTargets(),
        );
    }

    public function testFromGameIdThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        GameFactory::fromGameId(999);
    }

    // --- Users ---

    public function testGameWithNoUsersHasEmptyArray(): void
    {
        $gameId = $this->createGame();

        $game = GameFactory::fromGameId($gameId);

        $this->assertSame([], $game->getUsers());
    }

    public function testUserFieldsMappedCorrectly(): void
    {
        $gameId = $this->createGame();
        $this->createUser(telegramUserId: 200, firstName: 'Alice', lastName: 'Smith', username: 'alice');
        $this->createGameUser($gameId, telegramUserId: 200, time: '19:30');
        $this->createSlot($gameId, telegramUserId: 200, position: 1);

        $user = GameFactory::fromGameId($gameId)->getUsers()[0];

        $this->assertSame('1', $user->getNumber());
        $this->assertSame('Alice Smith', $user->getName());
        $this->assertSame('https://t.me/alice', $user->getLink());
        $this->assertSame('19:30', $user->getTime());
        $this->assertSame(0, $user->getVolleyball());
        $this->assertSame(0, $user->getNet());
    }

    public function testMultipleUsersOrderedByPosition(): void
    {
        $gameId = $this->createGame();
        $this->createUser(telegramUserId: 200, firstName: 'Alice');
        $this->createUser(telegramUserId: 201, firstName: 'Bob');
        $this->createGameUser($gameId, telegramUserId: 200);
        $this->createGameUser($gameId, telegramUserId: 201);
        $this->createSlot($gameId, telegramUserId: 201, position: 1);
        $this->createSlot($gameId, telegramUserId: 200, position: 2);

        $users = GameFactory::fromGameId($gameId)->getUsers();

        $this->assertCount(2, $users);
        $this->assertSame('Bob', $users[0]->getName());
        $this->assertSame('Alice', $users[1]->getName());
    }

    // --- Helpers ---

    private function createSlot(int $gameId, int $telegramUserId, int $position): void
    {
        $this->db->insert('game_slots', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'position' => $position,
        ]);
    }
}
