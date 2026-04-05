<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\PlayerRepository;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
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
        $this->assertSame('msg_1', $game->getInlineMessageId());
        $this->assertSame('Sunday Game 18:00', $game->getTitle());
    }

    public function testFromGameIdThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        GameFactory::fromGameId(999);
    }

    // --- fromInlineMessageId ---

    public function testFromInlineMessageIdReturnsGame(): void
    {
        $gameId = $this->createGame(title: 'Friday Game 19:00', inlineMessageId: 'msg_42');

        $game = GameFactory::fromInlineMessageId('msg_42');

        $this->assertSame($gameId, $game->getGameId());
        $this->assertSame('msg_42', $game->getInlineMessageId());
        $this->assertSame('Friday Game 19:00', $game->getTitle());
    }

    public function testFromInlineMessageIdThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        GameFactory::fromInlineMessageId('nonexistent');
    }

    // --- Players ---

    public function testGameWithNoPlayersHasEmptyArray(): void
    {
        $gameId = $this->createGame();

        $game = GameFactory::fromGameId($gameId);

        $this->assertSame([], $game->getPlayers());
    }

    public function testPlayerFieldsMappedCorrectly(): void
    {
        $gameId = $this->createGame();
        $this->createPlayer(telegramUserId: 200, firstName: 'Alice', lastName: 'Smith', username: 'alice');
        $this->createGamePlayer($gameId, telegramUserId: 200, time: '19:30');
        $this->createSlot($gameId, telegramUserId: 200, position: 1);

        $player = GameFactory::fromGameId($gameId)->getPlayers()[0];

        $this->assertSame('1', $player->getNumber());
        $this->assertSame('Alice Smith', $player->getName());
        $this->assertSame('https://t.me/alice', $player->getLink());
        $this->assertSame('19:30', $player->getTime());
        $this->assertSame(0, $player->getVolleyball());
        $this->assertSame(0, $player->getNet());
    }

    public function testMultiplePlayersOrderedByPosition(): void
    {
        $gameId = $this->createGame();
        $this->createPlayer(telegramUserId: 200, firstName: 'Alice');
        $this->createPlayer(telegramUserId: 201, firstName: 'Bob');
        $this->createGamePlayer($gameId, telegramUserId: 200);
        $this->createGamePlayer($gameId, telegramUserId: 201);
        $this->createSlot($gameId, telegramUserId: 201, position: 1);
        $this->createSlot($gameId, telegramUserId: 200, position: 2);

        $players = GameFactory::fromGameId($gameId)->getPlayers();

        $this->assertCount(2, $players);
        $this->assertSame('Bob', $players[0]->getName());
        $this->assertSame('Alice', $players[1]->getName());
    }

    // --- create ---

    public function testCreatePersistsGameToDatabase(): void
    {
        $data = $this->newGameData();

        $gameId = GameFactory::create($data);

        $game = new GameRepository($this->db)->findByInlineMessageId('msg_1');
        $this->assertNotNull($game);
        $this->assertSame($gameId, (int) $game['game_id']);
        $this->assertSame('Game 18:00', $game['title']);
    }

    public function testCreateUpsertsPlayer(): void
    {
        GameFactory::create($this->newGameData());

        $players = new PlayerRepository($this->db)->findAll();
        $this->assertCount(1, $players);
        $this->assertSame(200, $players[0]['telegram_user_id']);
        $this->assertSame('Danil', $players[0]['first_name']);
    }

    public function testCreatePersistsGamePlayerWithInitialEquipment(): void
    {
        $gameId = GameFactory::create($this->newGameData());

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertNotNull($gamePlayer);
        $this->assertSame(NewGameData::INITIAL_VOLLEYBALL, $gamePlayer['volleyball']);
        $this->assertSame(NewGameData::INITIAL_NET, $gamePlayer['net']);
    }

    public function testCreatePersistsSlotAtPositionOne(): void
    {
        $gameId = GameFactory::create($this->newGameData());

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int) $slots[0]['position']);
    }

    private function newGameData(): NewGameData
    {
        return NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Game 18:00',
            'query_1',
            'msg_1',
        );
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