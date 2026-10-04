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

    public function testFindEarliestTimePrefersPlayersWithAVolleyball(): void
    {
        $gameId = $this->createGame();
        $this->createGameUser($gameId, 200, '17:00');
        $this->createGameUser($gameId, 201, '19:00');
        $this->gameUserManager->incrementVolleyball($gameId, 201);

        $this->assertSame('19:00', $this->gameUserManager->findEarliestTime($gameId));
    }

    public function testFindEarliestTimePicksTheEarliestAcrossNetAndVolleyballHolders(): void
    {
        $gameId = $this->createGame();
        $this->createGameUser($gameId, 200, '15:00');
        $this->createGameUser($gameId, 201, '19:00');
        $this->createGameUser($gameId, 202, '17:00');
        $this->gameUserManager->incrementNet($gameId, 201);
        $this->gameUserManager->incrementVolleyball($gameId, 202);

        $this->assertSame('17:00', $this->gameUserManager->findEarliestTime($gameId));
    }

    public function testFindEarliestTimeCountsAPlayerWithBothNetAndVolleyball(): void
    {
        $gameId = $this->createGame();
        $this->createGameUser($gameId, 200, '17:00');
        $this->createGameUser($gameId, 201, '19:00');
        $this->gameUserManager->incrementNet($gameId, 201);
        $this->gameUserManager->incrementVolleyball($gameId, 201);

        $this->assertSame('19:00', $this->gameUserManager->findEarliestTime($gameId));
    }

    public function testFindEarliestTimeFallsBackOnceTheLastVolleyballIsRemoved(): void
    {
        $gameId = $this->createGame();
        $this->createGameUser($gameId, 200, '17:00');
        $this->createGameUser($gameId, 201, '19:00');
        $this->gameUserManager->incrementVolleyball($gameId, 201);
        $this->gameUserManager->decrementVolleyball($gameId, 201);

        $this->assertSame('17:00', $this->gameUserManager->findEarliestTime($gameId));
    }

    public function testFindEarliestTimeFallsBackToTheEarliestPlayer(): void
    {
        $gameId = $this->createGame();
        $this->createGameUser($gameId, 200, '19:00');
        $this->createGameUser($gameId, 201, '17:00');

        $this->assertSame('17:00', $this->gameUserManager->findEarliestTime($gameId));
    }

    public function testFindEarliestTimeReturnsNullWhenGameHasNoPlayers(): void
    {
        $gameId = $this->createGame();

        $this->assertNull($this->gameUserManager->findEarliestTime($gameId));
    }

    public function testFindUserIdsExceptListsTheOtherPlayersOfThatGame(): void
    {
        $gameId = $this->createGame();
        $otherGameId = $this->createGame(inlineMessageId: 'msg_2', gameKey: 'query_2');
        $this->createGameUser($gameId, 200);
        $this->createGameUser($gameId, 201);
        $this->createGameUser($gameId, 202);
        $this->createGameUser($otherGameId, 203);

        $this->assertSame([200, 202], $this->gameUserManager->findUserIdsExcept($gameId, 201));
    }

    public function testFindUserIdsExceptIsEmptyWhenOnlyTheExcludedUserPlays(): void
    {
        $gameId = $this->createGame();
        $this->createGameUser($gameId, 200);

        $this->assertSame([], $this->gameUserManager->findUserIdsExcept($gameId, 200));
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
