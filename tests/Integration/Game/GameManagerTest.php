<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\PlayerRepository;
use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\LeaveResult;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;

final class GameManagerTest extends DatabaseTestCase
{
    private GameManager $gameManager;

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        $this->gameManager = new GameManager();
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    // --- createGame ---

    public function testCreateGamePersistsGameToDatabase(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $game = new GameRepository($this->db)->findByInlineMessageId('msg_1');
        $this->assertNotNull($game);
        $this->assertSame($gameId, (int)$game['game_id']);
        $this->assertSame('Game 18:00', $game['title']);
    }

    public function testCreateGameUpsertsPlayer(): void
    {
        $this->gameManager->createGame($this->newGameData());

        $players = new PlayerRepository($this->db)->findAll();
        $this->assertCount(1, $players);
        $this->assertSame(200, $players[0]['telegram_user_id']);
        $this->assertSame('Danil', $players[0]['first_name']);
    }

    public function testCreateGamePersistsGamePlayerWithInitialEquipmentAndTime(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertNotNull($gamePlayer);
        $this->assertSame(NewGameData::INITIAL_VOLLEYBALL, $gamePlayer['volleyball']);
        $this->assertSame(NewGameData::INITIAL_NET, $gamePlayer['net']);
        $this->assertSame('18:00', $gamePlayer['time']);
    }

    public function testCreateGamePersistsSlotAtPositionOne(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int)$slots[0]['position']);
    }

    // --- joinGame ---

    public function testJoinGameCreatesGamePlayerAndSlot(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->joinGame($gameId, 200, 'Danil', null, null);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertNotNull($gamePlayer);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int)$slots[0]['position']);
    }

    public function testJoinGameUpsertsPlayer(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->joinGame($gameId, 200, 'Danil', 'Kashin', 'danil');

        $players = new PlayerRepository($this->db)->findAll();
        $this->assertCount(1, $players);
        $this->assertSame('Danil', $players[0]['first_name']);
        $this->assertSame('Kashin', $players[0]['last_name']);
    }

    public function testSecondJoinAddsExtraSlotWithoutDuplicatingGamePlayer(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->joinGame($gameId, 200, 'Danil', null, null);
        $this->gameManager->joinGame($gameId, 200, 'Danil', null, null);

        $gamePlayers = new GamePlayerRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $gamePlayers);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(2, $slots);
        $this->assertSame(2, (int)$slots[1]['position']);
    }

    // --- leaveGame ---

    public function testLeaveGameRemovesHighestSlot(): void
    {
        $gameId = $this->createGame();
        $this->seedPlayer($gameId, 200, position: 1);
        $this->createSlot($gameId, 200, 2);

        $result = $this->gameManager->leaveGame($gameId, 200);

        $this->assertSame(LeaveResult::Left, $result);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int)$slots[0]['position']);
    }

    public function testLeaveGameDeletesGamePlayerWhenLastSlot(): void
    {
        $gameId = $this->createGame();
        $this->seedPlayer($gameId, 200, position: 1);

        $result = $this->gameManager->leaveGame($gameId, 200);

        $this->assertSame(LeaveResult::Left, $result);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertNull($gamePlayer);
    }

    public function testLeaveGameReturnsNotJoinedWhenNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->gameManager->leaveGame($gameId, 200);

        $this->assertSame(LeaveResult::NotJoined, $result);
    }

    // --- addNet ---

    public function testAddNetIncrementsCount(): void
    {
        $gameId = $this->createGame();
        $this->seedPlayer($gameId, 200, position: 1);

        $result = $this->gameManager->addNet($gameId, 200);

        $this->assertSame(EquipmentResult::Added, $result);
        $this->assertSame(1, new GamePlayerRepository($this->db)->findNetCount($gameId, 200));
    }

    public function testAddNetReturnsNotJoinedWhenNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->gameManager->addNet($gameId, 200);

        $this->assertSame(EquipmentResult::NotJoined, $result);
    }

    // --- removeNet ---

    public function testRemoveNetDecrementsCount(): void
    {
        $gameId = $this->createGame();
        $this->seedPlayer($gameId, 200, position: 1, net: 2);

        $result = $this->gameManager->removeNet($gameId, 200);

        $this->assertSame(EquipmentResult::Removed, $result);
        $this->assertSame(1, new GamePlayerRepository($this->db)->findNetCount($gameId, 200));
    }

    public function testRemoveNetReturnsNoneLeftWhenZero(): void
    {
        $gameId = $this->createGame();
        $this->seedPlayer($gameId, 200, position: 1, net: 0);

        $result = $this->gameManager->removeNet($gameId, 200);

        $this->assertSame(EquipmentResult::NoneLeft, $result);
    }

    public function testRemoveNetReturnsNotJoinedWhenNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->gameManager->removeNet($gameId, 200);

        $this->assertSame(EquipmentResult::NotJoined, $result);
    }

    // --- addVolleyball ---

    public function testAddVolleyballIncrementsCount(): void
    {
        $gameId = $this->createGame();
        $this->seedPlayer($gameId, 200, position: 1);

        $result = $this->gameManager->addVolleyball($gameId, 200);

        $this->assertSame(EquipmentResult::Added, $result);
        $this->assertSame(1, new GamePlayerRepository($this->db)->findVolleyballCount($gameId, 200));
    }

    public function testAddVolleyballReturnsNotJoinedWhenNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->gameManager->addVolleyball($gameId, 200);

        $this->assertSame(EquipmentResult::NotJoined, $result);
    }

    // --- removeVolleyball ---

    public function testRemoveVolleyballDecrementsCount(): void
    {
        $gameId = $this->createGame();
        $this->seedPlayer($gameId, 200, position: 1, volleyball: 2);

        $result = $this->gameManager->removeVolleyball($gameId, 200);

        $this->assertSame(EquipmentResult::Removed, $result);
        $this->assertSame(1, new GamePlayerRepository($this->db)->findVolleyballCount($gameId, 200));
    }

    public function testRemoveVolleyballReturnsNoneLeftWhenZero(): void
    {
        $gameId = $this->createGame();
        $this->seedPlayer($gameId, 200, position: 1, volleyball: 0);

        $result = $this->gameManager->removeVolleyball($gameId, 200);

        $this->assertSame(EquipmentResult::NoneLeft, $result);
    }

    public function testRemoveVolleyballReturnsNotJoinedWhenNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->gameManager->removeVolleyball($gameId, 200);

        $this->assertSame(EquipmentResult::NotJoined, $result);
    }

    // --- setLocation ---

    public function testSetLocationUpdatesGame(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->setLocation($gameId, '55.751244,37.618423');

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertSame('55.751244,37.618423', $game['location']);
    }

    // --- joinWithTime ---

    public function testJoinWithTimeCreatesNewPlayerWithTime(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->setPlayerTime($gameId, 200, 'Danil', null, null, '19:30');

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertNotNull($gamePlayer);
        $this->assertSame('19:30', $gamePlayer['time']);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
    }

    public function testJoinWithTimeUpdatesTimeForExistingPlayer(): void
    {
        $gameId = $this->createGame();
        $this->seedPlayer($gameId, 200, position: 1);

        $this->gameManager->setPlayerTime($gameId, 200, 'Danil', null, null, '20:00');

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame('20:00', $gamePlayer['time']);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
    }

    // --- resolveGameIdByInlineMessageId ---

    public function testResolveGameIdByInlineMessageIdReturnsId(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_42');

        $this->assertSame($gameId, $this->gameManager->resolveGameIdByInlineMessageId('msg_42'));
    }

    public function testResolveGameIdByInlineMessageIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->gameManager->resolveGameIdByInlineMessageId('nonexistent'));
    }

    // --- resolveGameByInlineQueryId ---

    public function testResolveGameByInlineQueryIdReturnsResult(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_42', inlineQueryId: 'query_42');

        $result = $this->gameManager->resolveGameByInlineQueryId('query_42');

        $this->assertNotNull($result);
        $this->assertSame($gameId, $result->gameId);
        $this->assertSame('msg_42', $result->inlineMessageId);
    }

    public function testResolveGameByInlineQueryIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->gameManager->resolveGameByInlineQueryId('nonexistent'));
    }

    // --- recalculateGameTime ---

    public function testAddNetRecalculatesGameTimeToEarliestNetHolder(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedPlayer($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedPlayer($gameId, 201, position: 2, net: 0, time: '16:00');

        $this->gameManager->addNet($gameId, 201);

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 16:00', $title);
    }

    public function testRemoveNetRecalculatesGameTimeToNextNetHolder(): void
    {
        $gameId = $this->createGame(title: 'Beach 16:00');
        $this->seedPlayer($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedPlayer($gameId, 201, position: 2, net: 1, time: '16:00');

        $this->gameManager->removeNet($gameId, 201);

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 18:00', $title);
    }

    public function testSetPlayerTimeRecalculatesGameTime(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedPlayer($gameId, 200, position: 1, net: 1, time: '18:00');

        $this->gameManager->setPlayerTime($gameId, 200, 'Danil', null, null, '15:30');

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 15:30', $title);
    }

    public function testRecalculateGameTimeIgnoresPlayersWithoutNets(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedPlayer($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedPlayer($gameId, 201, position: 2, net: 0, time: '15:00');

        $this->gameManager->addVolleyball($gameId, 201);

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 18:00', $title);
    }

    public function testRecalculateGameTimeReplacesShortTimeFormatInTitle(): void
    {
        $gameId = $this->createGame(title: 'Beach 8:00');
        $this->seedPlayer($gameId, 200, position: 1, net: 1, time: '08:00');
        $this->seedPlayer($gameId, 201, position: 2, net: 0, time: '07:30');

        $this->gameManager->addNet($gameId, 201);

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 07:30', $title);
    }

    public function testRecalculateGameTimeKeepsTitleWhenNoChange(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedPlayer($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedPlayer($gameId, 201, position: 2, net: 0, time: '18:00');

        $this->gameManager->addNet($gameId, 201);

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 18:00', $title);
    }

    // --- Helpers ---

    private function newGameData(): NewGameData
    {
        return NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Game 18:00',
            'query_1',
            'msg_1',
        );
    }

    private function seedPlayer(
        int $gameId,
        int $telegramUserId,
        int $position,
        int $volleyball = 0,
        int $net = 0,
        ?string $time = null,
    ): void {
        $this->createPlayer($telegramUserId);
        $this->db->insert('game_players', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'volleyball' => $volleyball,
            'net' => $net,
            'time' => $time,
        ]);
        $this->createSlot($gameId, $telegramUserId, $position);
    }

    private function createSlot(int $gameId, int $telegramUserId, int $position): void
    {
        $this->db->insert('game_slots', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'position' => $position,
        ]);
    }
}
