<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Game\AdminGameManager;
use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;

final class GameManagerAdminTest extends DatabaseTestCase
{
    private GameManager $gameManager;

    private AdminGameManager $adminGameManager;

    public function testIncrementNetAddsNet(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);

        $result = $this->adminGameManager->adminAddNet($gameId, 200);

        $this->assertSame(EquipmentResult::Added, $result);
        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame(1, (int)$gameUser['net']);
    }

    private function createGameWithUserSlot(int $telegramUserId, int $position): int
    {
        $gameId = $this->createGame(title: 'Test 18:00');
        $this->createUser($telegramUserId);
        $this->db->insert('game_users', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'time' => '18:00',
        ]);
        $this->addSlot($gameId, $telegramUserId, $position);

        return $gameId;
    }

    // --- incrementNet ---

    private function addSlot(int $gameId, int $telegramUserId, int $position): void
    {
        $this->db->insert('game_slots', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'position' => $position,
        ]);
    }

    public function testIncrementNetReturnsNotJoinedWhenUserNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->adminGameManager->adminAddNet($gameId, 999);

        $this->assertSame(EquipmentResult::NotJoined, $result);
    }

    // --- incrementVolleyball ---

    public function testIncrementVolleyballAddsVolleyball(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);

        $result = $this->adminGameManager->adminAddVolleyball($gameId, 200);

        $this->assertSame(EquipmentResult::Added, $result);
        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame(1, (int)$gameUser['volleyball']);
    }

    public function testIncrementVolleyballReturnsNotJoinedWhenUserNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->adminGameManager->adminAddVolleyball($gameId, 999);

        $this->assertSame(EquipmentResult::NotJoined, $result);
    }

    // --- removeLocation ---

    public function testRemoveLocationClearsValue(): void
    {
        $gameId = $this->createGame();
        $this->db->update('games', ['location' => '55.7,37.6'], ['game_id' => $gameId]);

        $this->gameManager->removeLocation($gameId);

        $game = $this->db->get('games', '*', ['game_id' => $gameId]);
        $this->assertNull($game['location']);
    }

    // --- isUserInGame ---

    public function testIsUserInGameReturnsTrueWhenUserExists(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);

        $this->assertTrue($this->gameManager->isUserInGame($gameId, 200));
    }

    public function testIsUserInGameReturnsFalseWhenUserDoesNotExist(): void
    {
        $gameId = $this->createGame();

        $this->assertFalse($this->gameManager->isUserInGame($gameId, 999));
    }

    // --- incrementNet: multiple increments ---

    public function testIncrementNetMultipleTimesAccumulates(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);

        $this->adminGameManager->adminAddNet($gameId, 200);
        $this->adminGameManager->adminAddNet($gameId, 200);

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame(2, (int)$gameUser['net']);
    }

    // --- incrementVolleyball: multiple increments ---

    public function testIncrementVolleyballMultipleTimesAccumulates(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);

        $this->adminGameManager->adminAddVolleyball($gameId, 200);
        $this->adminGameManager->adminAddVolleyball($gameId, 200);
        $this->adminGameManager->adminAddVolleyball($gameId, 200);

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame(3, (int)$gameUser['volleyball']);
    }

    // --- setLocation: from coordinates ---

    public function testSetLocationSetsValueFromCoordinates(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->setLocation($gameId, 55.7, 37.6);

        $game = $this->db->get('games', '*', ['game_id' => $gameId]);
        $this->assertSame('55.7,37.6', $game['location']);
    }

    // --- helpers ---

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        $this->gameManager = new GameManager();
        $this->adminGameManager = new AdminGameManager();
    }

    protected function tearDown(): void
    {
        Connection::close();
    }
}
