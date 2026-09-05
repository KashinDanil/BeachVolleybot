<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameMessageRepository;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\LeaveResult;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\Telegram\Messages\Targets\ChatGameMessageTarget;
use BeachVolleybot\Telegram\Messages\Targets\InlineGameMessageTarget;
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

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertNotNull($game);
        $this->assertSame('query_1', $game['game_key']);
        $this->assertSame('Game 18:00', $game['title']);
    }

    public function testCreateGameStoresKickoffAndVenueFromTitle(): void
    {
        $data = NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Somorrostro 31.12.2099 18:00',
            'query_1',
        );

        $gameId = $this->gameManager->createGame($data);

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertSame('2099-12-31 18:00:00', $game['kickoff_at']);
        $this->assertSame('Somorrostro', $game['venue_name']);
    }

    public function testAddInlineMessageAttachesToJunctionTable(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());
        $this->gameManager->addInlineMessage($gameId, 'msg_1');

        $targets = new GameMessageRepository($this->db)->findTargetsByGameId($gameId);
        $this->assertEquals([new InlineGameMessageTarget('msg_1')], $targets);
    }

    public function testAddChatMessageAttachesToJunctionTable(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());
        $this->gameManager->addChatMessage($gameId, -100, 77);

        $targets = new GameMessageRepository($this->db)->findTargetsByGameId($gameId);
        $this->assertEquals([new ChatGameMessageTarget(-100, 77)], $targets);
    }

    public function testCreateGameUpsertsUser(): void
    {
        $this->gameManager->createGame($this->newGameData());

        $users = new UserRepository($this->db)->findAll();
        $this->assertCount(1, $users);
        $this->assertSame(200, $users[0]['telegram_user_id']);
        $this->assertSame('Danil', $users[0]['first_name']);
    }

    public function testCreateGamePersistsGameUserWithInitialEquipmentAndTime(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertNotNull($gameUser);
        $this->assertSame(NewGameData::INITIAL_VOLLEYBALL, $gameUser['volleyball']);
        $this->assertSame(NewGameData::INITIAL_NET, $gameUser['net']);
        $this->assertSame('18:00', $gameUser['time']);
    }

    public function testCreateGameNormalizesShortTimeFormatInTitle(): void
    {
        $gameId = $this->gameManager->createGame(
            NewGameData::fromUser(
                new TelegramUser(id: 200, firstName: 'Danil'),
                'Beach 8:00',
                'query_1',
            ),
        );

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertSame('Beach 08:00', $game['title']);

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame('08:00', $gameUser['time']);
    }

    public function testCreateGamePersistsSlotAtPositionOne(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int)$slots[0]['position']);
    }

    // --- joinGame ---

    public function testJoinGameCreatesGameUserAndSlot(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->joinGame($gameId, 200, 'Danil', null, null);

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertNotNull($gameUser);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int)$slots[0]['position']);
    }

    public function testJoinGameUpsertsUser(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->joinGame($gameId, 200, 'Danil', 'Kashin', 'danil');

        $users = new UserRepository($this->db)->findAll();
        $this->assertCount(1, $users);
        $this->assertSame('Danil', $users[0]['first_name']);
        $this->assertSame('Kashin', $users[0]['last_name']);
    }

    public function testSecondJoinAddsExtraSlotWithoutDuplicatingGameUser(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->joinGame($gameId, 200, 'Danil', null, null);
        $this->gameManager->joinGame($gameId, 200, 'Danil', null, null);

        $gameUsers = new GameUserRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $gameUsers);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(2, $slots);
        $this->assertSame(2, (int)$slots[1]['position']);
    }

    // --- leaveGame ---

    public function testLeaveGameRemovesHighestSlot(): void
    {
        $gameId = $this->createGame();
        $this->seedUser($gameId, 200, position: 1);
        $this->createSlot($gameId, 200, 2);

        $result = $this->gameManager->leaveGame($gameId, 200);

        $this->assertSame(LeaveResult::Left, $result);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int)$slots[0]['position']);
    }

    public function testLeaveGameDeletesGameUserWhenLastSlot(): void
    {
        $gameId = $this->createGame();
        $this->seedUser($gameId, 200, position: 1);

        $result = $this->gameManager->leaveGame($gameId, 200);

        $this->assertSame(LeaveResult::Left, $result);

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertNull($gameUser);
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
        $this->seedUser($gameId, 200, position: 1);

        $result = $this->gameManager->addNet($gameId, 200, 'Danil', null, null);

        $this->assertSame(EquipmentResult::Added, $result);
        $this->assertSame(1, new GameUserRepository($this->db)->findNetCount($gameId, 200));
    }

    public function testAddNetAutoJoinsUserWhenNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->gameManager->addNet($gameId, 200, 'Danil', null, null);

        $this->assertSame(EquipmentResult::Added, $result);
        $this->assertNotNull(new GameUserRepository($this->db)->findByGameUser($gameId, 200));
        $this->assertSame(1, new GameUserRepository($this->db)->findNetCount($gameId, 200));

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(200, (int)$slots[0]['telegram_user_id']);
    }

    public function testAddNetDoesNotDuplicateSlotForExistingUser(): void
    {
        $gameId = $this->createGame();
        $this->seedUser($gameId, 200, position: 1);

        $this->gameManager->addNet($gameId, 200, 'Danil', null, null);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
    }

    // --- removeNet ---

    public function testRemoveNetDecrementsCount(): void
    {
        $gameId = $this->createGame();
        $this->seedUser($gameId, 200, position: 1, net: 2);

        $result = $this->gameManager->removeNet($gameId, 200);

        $this->assertSame(EquipmentResult::Removed, $result);
        $this->assertSame(1, new GameUserRepository($this->db)->findNetCount($gameId, 200));
    }

    public function testRemoveNetReturnsNoneLeftWhenZero(): void
    {
        $gameId = $this->createGame();
        $this->seedUser($gameId, 200, position: 1, net: 0);

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
        $this->seedUser($gameId, 200, position: 1);

        $result = $this->gameManager->addVolleyball($gameId, 200, 'Danil', null, null);

        $this->assertSame(EquipmentResult::Added, $result);
        $this->assertSame(1, new GameUserRepository($this->db)->findVolleyballCount($gameId, 200));
    }

    public function testAddVolleyballAutoJoinsUserWhenNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->gameManager->addVolleyball($gameId, 200, 'Danil', null, null);

        $this->assertSame(EquipmentResult::Added, $result);
        $this->assertNotNull(new GameUserRepository($this->db)->findByGameUser($gameId, 200));
        $this->assertSame(1, new GameUserRepository($this->db)->findVolleyballCount($gameId, 200));

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(200, (int)$slots[0]['telegram_user_id']);
    }

    public function testAddVolleyballDoesNotDuplicateSlotForExistingUser(): void
    {
        $gameId = $this->createGame();
        $this->seedUser($gameId, 200, position: 1);

        $this->gameManager->addVolleyball($gameId, 200, 'Danil', null, null);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
    }

    // --- removeVolleyball ---

    public function testRemoveVolleyballDecrementsCount(): void
    {
        $gameId = $this->createGame();
        $this->seedUser($gameId, 200, position: 1, volleyball: 2);

        $result = $this->gameManager->removeVolleyball($gameId, 200);

        $this->assertSame(EquipmentResult::Removed, $result);
        $this->assertSame(1, new GameUserRepository($this->db)->findVolleyballCount($gameId, 200));
    }

    public function testRemoveVolleyballReturnsNoneLeftWhenZero(): void
    {
        $gameId = $this->createGame();
        $this->seedUser($gameId, 200, position: 1, volleyball: 0);

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

        $this->gameManager->setLocation($gameId, 55.751244, 37.618423);

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertSame('55.751244,37.618423', $game['location']);
    }

    // --- joinWithTime ---

    public function testJoinWithTimeCreatesNewUserWithTime(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->setUserTime($gameId, 200, 'Danil', null, null, '19:30');

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertNotNull($gameUser);
        $this->assertSame('19:30', $gameUser['time']);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
    }

    public function testSetUserTimeDoesNotDuplicateSlotForExistingUser(): void
    {
        $gameId = $this->createGame();
        $this->seedUser($gameId, 200, position: 1);

        $this->gameManager->setUserTime($gameId, 200, 'Danil', null, null, '20:00');

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame('20:00', $gameUser['time']);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
    }

    // --- resolveGameIdByGameKey ---

    public function testResolveGameIdByInlineQueryIdReturnsId(): void
    {
        $gameId = $this->createGame(gameKey: 'query_42');

        $this->assertSame($gameId, $this->gameManager->resolveGameIdByGameKey('query_42'));
    }

    public function testResolveGameIdByInlineQueryIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->gameManager->resolveGameIdByGameKey('nonexistent'));
    }

    // --- recalculateGameTime ---

    public function testAddNetRecalculatesGameTimeToEarliestNetHolder(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedUser($gameId, 201, position: 2, net: 0, time: '16:00');

        $this->gameManager->addNet($gameId, 201, 'Alice', null, null);

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 16:00', $title);
    }

    public function testRecalculatedGameTimeAlsoRewritesKickoff(): void
    {
        $gameId = $this->createGame(title: 'Beach 31.12.2099 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedUser($gameId, 201, position: 2, net: 0, time: '16:00');

        $this->gameManager->addNet($gameId, 201, 'Alice', null, null);

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertSame('Beach 31.12.2099 16:00', $game['title']);
        $this->assertSame('2099-12-31 16:00:00', $game['kickoff_at']);
    }

    public function testRemoveNetRecalculatesGameTimeToNextNetHolder(): void
    {
        $gameId = $this->createGame(title: 'Beach 16:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedUser($gameId, 201, position: 2, net: 1, time: '16:00');

        $this->gameManager->removeNet($gameId, 201);

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 18:00', $title);
    }

    public function testSetUserTimeRecalculatesGameTime(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');

        $this->gameManager->setUserTime($gameId, 200, 'Danil', null, null, '15:30');

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 15:30', $title);
    }

    public function testRecalculateGameTimeIgnoresUsersWithoutNets(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedUser($gameId, 201, position: 2, net: 0, time: '15:00');

        $this->gameManager->addVolleyball($gameId, 201, 'Alice', null, null);

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 18:00', $title);
    }

    public function testRecalculateGameTimeReplacesShortTimeFormatInTitle(): void
    {
        $gameId = $this->createGame(title: 'Beach 8:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '08:00');
        $this->seedUser($gameId, 201, position: 2, net: 0, time: '07:30');

        $this->gameManager->addNet($gameId, 201, 'Alice', null, null);

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 07:30', $title);
    }

    public function testRemoveLastNetFallsBackToEarliestTimeAmongAllUsers(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedUser($gameId, 201, position: 2, net: 0, time: '16:00');

        $this->gameManager->removeNet($gameId, 200);

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 16:00', $title);
    }

    public function testRecalculateGameTimeKeepsTitleWhenNoChange(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedUser($gameId, 201, position: 2, net: 0, time: '18:00');

        $this->gameManager->addNet($gameId, 201, 'Alice', null, null);

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach 18:00', $title);
    }

    // --- changeTitle ---

    public function testChangeTitleWhenCreatorIsOnlyUserUsesProposedTime(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->gameManager->changeTitle($gameId, 200, 'Danil', null, null, 'Beach Saturday 20:00');

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach Saturday 20:00', $title);
    }

    public function testChangeTitleRewritesKickoffAndVenue(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->gameManager->changeTitle($gameId, 200, 'Danil', null, null, 'Bogatell 31.12.2099 20:00');

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertSame('2099-12-31 20:00:00', $game['kickoff_at']);
        $this->assertSame('Bogatell', $game['venue_name']);
    }

    public function testChangeTitleUpdatesCreatorUserTime(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->gameManager->changeTitle($gameId, 200, 'Danil', null, null, 'Beach Saturday 20:00');

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame('20:00', $gameUser['time']);
    }

    public function testChangeTitlePreservesEarlierUserTimeInTitle(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00'); // creator
        $this->seedUser($gameId, 201, position: 2, net: 1, time: '16:00');

        $this->gameManager->changeTitle($gameId, 200, 'Danil', null, null, 'Picnic Sunday 20:00');

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Picnic Sunday 16:00', $title);
    }

    public function testChangeTitleLeavesOtherUsersTimesUnchanged(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00'); // creator
        $this->seedUser($gameId, 201, position: 2, net: 1, time: '16:00');

        $this->gameManager->changeTitle($gameId, 200, 'Danil', null, null, 'Picnic Sunday 20:00');

        $creatorTime = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $otherTime = new GameUserRepository($this->db)->findByGameUser($gameId, 201);
        $this->assertSame('20:00', $creatorTime['time']);
        $this->assertSame('16:00', $otherTime['time']);
    }

    public function testChangeTitleNormalizesShortTimeFormat(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->gameManager->changeTitle($gameId, 200, 'Danil', null, null, 'Beach Saturday 9:00');

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Beach Saturday 09:00', $title);
    }

    public function testChangeTitleKeepsCreatorTimeWhenUnchanged(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->gameManager->changeTitle($gameId, 200, 'Danil', null, null, 'Picnic Sunday 18:00');

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Picnic Sunday 18:00', $title);
    }

    // --- Helpers ---

    private function newGameData(): NewGameData
    {
        return NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Game 18:00',
            'query_1',
        );
    }

    private function seedUser(
        int $gameId,
        int $telegramUserId,
        int $position,
        int $volleyball = 0,
        int $net = 0,
        string $time = '18:00',
    ): void {
        $this->createUser($telegramUserId);
        $this->db->insert('game_users', [
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
