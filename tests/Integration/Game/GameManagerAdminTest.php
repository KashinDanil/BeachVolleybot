<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GamePlayerRepository;
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
        $gameId = $this->createGameWithPlayerSlot(200, 1);

        $result = $this->adminGameManager->adminAddNet($gameId, 200);

        $this->assertSame(EquipmentResult::Added, $result);
        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(1, (int)$gamePlayer['net']);
    }

    private function createGameWithPlayerSlot(int $telegramUserId, int $position): int
    {
        $gameId = $this->createGame(title: 'Test 18:00');
        $this->createPlayer($telegramUserId);
        $this->db->insert('game_players', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
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

    public function testIncrementNetReturnsNotJoinedWhenPlayerNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->adminGameManager->adminAddNet($gameId, 999);

        $this->assertSame(EquipmentResult::NotJoined, $result);
    }

    // --- incrementVolleyball ---

    public function testIncrementVolleyballAddsVolleyball(): void
    {
        $gameId = $this->createGameWithPlayerSlot(200, 1);

        $result = $this->adminGameManager->adminAddVolleyball($gameId, 200);

        $this->assertSame(EquipmentResult::Added, $result);
        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(1, (int)$gamePlayer['volleyball']);
    }

    public function testIncrementVolleyballReturnsNotJoinedWhenPlayerNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->adminGameManager->adminAddVolleyball($gameId, 999);

        $this->assertSame(EquipmentResult::NotJoined, $result);
    }

    // --- setLocation with null ---

    public function testSetLocationAcceptsNull(): void
    {
        $gameId = $this->createGame();
        $this->db->update('games', ['location' => '55.7,37.6'], ['game_id' => $gameId]);

        $this->gameManager->setLocation($gameId, null);

        $game = $this->db->get('games', '*', ['game_id' => $gameId]);
        $this->assertNull($game['location']);
    }

    // --- isPlayerInGame ---

    public function testIsPlayerInGameReturnsTrueWhenPlayerExists(): void
    {
        $gameId = $this->createGameWithPlayerSlot(200, 1);

        $this->assertTrue($this->gameManager->isPlayerInGame($gameId, 200));
    }

    public function testIsPlayerInGameReturnsFalseWhenPlayerDoesNotExist(): void
    {
        $gameId = $this->createGame();

        $this->assertFalse($this->gameManager->isPlayerInGame($gameId, 999));
    }

    // --- incrementNet: multiple increments ---

    public function testIncrementNetMultipleTimesAccumulates(): void
    {
        $gameId = $this->createGameWithPlayerSlot(200, 1);

        $this->adminGameManager->adminAddNet($gameId, 200);
        $this->adminGameManager->adminAddNet($gameId, 200);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(2, (int)$gamePlayer['net']);
    }

    // --- incrementVolleyball: multiple increments ---

    public function testIncrementVolleyballMultipleTimesAccumulates(): void
    {
        $gameId = $this->createGameWithPlayerSlot(200, 1);

        $this->adminGameManager->adminAddVolleyball($gameId, 200);
        $this->adminGameManager->adminAddVolleyball($gameId, 200);
        $this->adminGameManager->adminAddVolleyball($gameId, 200);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(3, (int)$gamePlayer['volleyball']);
    }

    // --- setLocation: set non-null ---

    public function testSetLocationSetsValue(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->setLocation($gameId, '55.7,37.6');

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
