<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;

final class GameUserManagerTest extends DatabaseTestCase
{
    private GameUserManager $gameUserManager;

    public function testIsUserInGameReturnsTrueWhenUserExists(): void
    {
        $gameId = $this->createGame();
        $this->createGameUser($gameId, 200);

        $this->assertTrue($this->gameUserManager->isUserInGame($gameId, 200));
    }

    public function testIsUserInGameReturnsFalseWhenUserDoesNotExist(): void
    {
        $gameId = $this->createGame();

        $this->assertFalse($this->gameUserManager->isUserInGame($gameId, 999));
    }

    public function testFindGameUserRecordHydratesTheRow(): void
    {
        $gameId = $this->createGame();
        $this->createUser(200);
        $this->gameUserManager->createGameUser($gameId, 200, '19:30', volleyball: 2, net: 1);

        $gameUser = $this->gameUserManager->findGameUserRecord($gameId, 200);

        $this->assertSame($gameId, $gameUser->gameId);
        $this->assertSame(200, $gameUser->telegramUserId);
        $this->assertSame('19:30', $gameUser->time);
        $this->assertSame(2, $gameUser->volleyball);
        $this->assertSame(1, $gameUser->net);
    }

    public function testFindGameUserRecordReturnsNullWhenUserIsNotInGame(): void
    {
        $gameId = $this->createGame();

        $this->assertNull($this->gameUserManager->findGameUserRecord($gameId, 999));
    }

    public function testFindEarliestTimePrefersPlayersWithANet(): void
    {
        $gameId = $this->createGame();
        $this->createGameUser($gameId, 200, '17:00');
        $this->createGameUser($gameId, 201, '19:00');
        $this->gameUserManager->incrementNet($gameId, 201);

        $this->assertSame('19:00', $this->gameUserManager->findEarliestTime($gameId));
    }

    public function testFindEarliestTimeFallsBackToTheEarliestPlayer(): void
    {
        $gameId = $this->createGame();
        $this->createGameUser($gameId, 200, '19:00');
        $this->createGameUser($gameId, 201, '17:00');

        $this->assertSame('17:00', $this->gameUserManager->findEarliestTime($gameId));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        $this->gameUserManager = new GameUserManager();
    }

    protected function tearDown(): void
    {
        Connection::close();
    }
}
