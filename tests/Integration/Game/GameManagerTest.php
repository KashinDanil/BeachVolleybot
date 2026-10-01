<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameMessageRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\Timestamp;
use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\LeaveResult;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Game\NewGameFactory;
use BeachVolleybot\Notifications\MinimumPlayersNotifier;
use BeachVolleybot\Notifications\NotificationEnqueuer;
use BeachVolleybot\Telegram\Messages\GameMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\Validator\Rules\Game\MinimumPlayersPerNetRule;
use DateTimeImmutable;
use InvalidArgumentException;

final class GameManagerTest extends DatabaseTestCase
{
    private GameManager $gameManager;

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        SpyQueue::reset();
        $this->gameManager = new GameManager(new MinimumPlayersNotifier(new NotificationEnqueuer(SpyQueue::class, sys_get_temp_dir())));
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    // --- createGame ---

    public function testCreateGamePersistsGameToDatabase(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $game = $this->gameRecord($gameId);
        $this->assertSame('query_1', $game->gameKey);
        $this->assertSame('Game 18:00', $game->title);
    }

    public function testCreateGameStoresTheCreatorsLanguageCode(): void
    {
        $this->gameManager->createGame(NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil', languageCode: 'es'),
            'Game 18:00',
            'query_1',
        ));

        $this->assertSame('es', new UserManager()->findUserRecordById(200)?->languageCode);
    }

    public function testCreateGameStoresKickoffAndVenueFromTitle(): void
    {
        $data = NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Somorrostro 31.12.2099 18:00',
            'query_1',
        );

        $gameId = $this->gameManager->createGame($data);

        $game = $this->gameRecord($gameId);
        $this->assertSame('2099-12-31 17:00:00', Timestamp::format($game->kickoffAt));
        $this->assertSame('Somorrostro', $game->venueName);
    }

    public function testPostedCardAndStoredRowShareOneKickoff(): void
    {
        // A bare weekday is anchored on the creation instant. Pinned to the last second of a
        // Friday, so anything reading the clock a second later would resolve a week further out.
        $data = NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Somorrostro Saturday 18:00',
            'query_1',
            new DateTimeImmutable('2026-08-14 23:59:59'),
        );

        $card = NewGameFactory::create($data);
        $gameId = $this->gameManager->createGame($data);

        $game = $this->gameRecord($gameId);
        // One kickoff, two readings: 16:00Z in the column, 18:00 on the card's Barcelona clock.
        $this->assertSame('2026-08-15 16:00:00', Timestamp::format($game->kickoffAt));
        $this->assertSame('2026-08-15 18:00:00', $card->getKickoffAt()->format('Y-m-d H:i:s'));
    }

    public function testAddInlineMessageAttachesToJunctionTable(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());
        $this->gameManager->addInlineMessage($gameId, 'msg_1', 'query_1');

        $messages = new GameMessageRepository($this->db)->findByGameId($gameId);
        $this->assertEquals([new GameMessage(inlineMessageId: 'msg_1', inlineQueryId: 'query_1')], $messages);
    }

    public function testAddChatMessageAttachesToJunctionTable(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());
        $this->gameManager->addChatMessage($gameId, -100, 77);

        $messages = new GameMessageRepository($this->db)->findByGameId($gameId);
        $this->assertEquals([new GameMessage(chatId: -100, messageId: 77)], $messages);
    }

    public function testCreateGameUpsertsUser(): void
    {
        $this->gameManager->createGame($this->newGameData());

        $userManager = new UserManager();
        $this->assertSame(1, $userManager->countUsers());
        $this->assertSame('Danil', $userManager->findUserRecordById(200)?->firstName);
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

        $game = $this->gameRecord($gameId);
        $this->assertSame('Beach 08:00', $game->title);

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

        $this->gameManager->joinGame($gameId, new TelegramUser(id: 200, firstName: 'Danil'));

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertNotNull($gameUser);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int)$slots[0]['position']);
    }

    public function testJoinGameUpsertsUser(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->joinGame($gameId, new TelegramUser(id: 200, firstName: 'Danil', lastName: 'Kashin', username: 'danil'));

        $userManager = new UserManager();
        $user = $userManager->findUserRecordById(200);
        $this->assertSame(1, $userManager->countUsers());
        $this->assertSame('Danil', $user?->firstName);
        $this->assertSame('Kashin', $user?->lastName);
    }

    public function testJoinGameStoresTheLanguageCode(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->joinGame($gameId, new TelegramUser(id: 200, firstName: 'Danil', languageCode: 'ru'));

        $this->assertSame('ru', new UserManager()->findUserRecordById(200)?->languageCode);
    }

    public function testSecondJoinAddsExtraSlotWithoutDuplicatingGameUser(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->joinGame($gameId, new TelegramUser(id: 200, firstName: 'Danil'));
        $this->gameManager->joinGame($gameId, new TelegramUser(id: 200, firstName: 'Danil'));

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

        $result = $this->gameManager->addNet($gameId, new TelegramUser(id: 200, firstName: 'Danil'));

        $this->assertSame(EquipmentResult::Added, $result);
        $this->assertSame(1, new GameUserRepository($this->db)->findNetCount($gameId, 200));
    }

    public function testAddNetAutoJoinsUserWhenNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->gameManager->addNet($gameId, new TelegramUser(id: 200, firstName: 'Danil'));

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

        $this->gameManager->addNet($gameId, new TelegramUser(id: 200, firstName: 'Danil'));

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

        $result = $this->gameManager->addVolleyball($gameId, new TelegramUser(id: 200, firstName: 'Danil'));

        $this->assertSame(EquipmentResult::Added, $result);
        $this->assertSame(1, new GameUserRepository($this->db)->findVolleyballCount($gameId, 200));
    }

    public function testAddVolleyballAutoJoinsUserWhenNotInGame(): void
    {
        $gameId = $this->createGame();

        $result = $this->gameManager->addVolleyball($gameId, new TelegramUser(id: 200, firstName: 'Danil'));

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

        $this->gameManager->addVolleyball($gameId, new TelegramUser(id: 200, firstName: 'Danil'));

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

        $game = $this->gameRecord($gameId);
        $this->assertSame('55.751244,37.618423', $game->location);
    }

    // --- settings ---

    public function testSetPlayersPerNetPersistsTheLimit(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->setPlayersPerNet($gameId, 6);

        $this->assertSame(6, $this->gameRecord($gameId)->settings->playersPerNet);
    }

    public function testSetPlayersPerNetReplacesAnEarlierLimit(): void
    {
        $gameId = $this->createGame();
        $this->gameManager->setPlayersPerNet($gameId, 6);

        $this->gameManager->setPlayersPerNet($gameId, 8);

        $this->assertSame(8, $this->gameRecord($gameId)->settings->playersPerNet);
    }

    public function testSetPlayersPerNetNullClearsTheLimit(): void
    {
        $gameId = $this->createGame();
        $this->gameManager->setPlayersPerNet($gameId, 6);

        $this->gameManager->setPlayersPerNet($gameId, null);

        $this->assertNull($this->gameRecord($gameId)->settings->playersPerNet);
    }

    public function testSetPlayersPerNetReadsTheStoredSettingsBeforeWriting(): void
    {
        $gameId = $this->createGame();
        $this->gameManager->setPlayersPerNet($gameId, 6);

        $selects = $this->selectsAgainstGames($this->queriesDuring(
            fn() => $this->gameManager->setPlayersPerNet($gameId, 8),
        ));

        $this->assertNotEmpty($selects);
    }

    public function testSetPlayersPerNetRejectsAValueBelowTheMinimum(): void
    {
        $gameId = $this->createGame();

        $this->expectException(InvalidArgumentException::class);

        $this->gameManager->setPlayersPerNet($gameId, MinimumPlayersPerNetRule::MINIMUM - 1);
    }

    public function testSetPlayersPerNetRejectsZeroWhichWouldReserveTheWholeRoster(): void
    {
        $gameId = $this->createGame();

        $this->expectException(InvalidArgumentException::class);

        $this->gameManager->setPlayersPerNet($gameId, 0);
    }

    public function testSetPlayersPerNetRejectsANegativeLimitThatWouldNeverApply(): void
    {
        $gameId = $this->createGame();

        $this->expectException(InvalidArgumentException::class);

        $this->gameManager->setPlayersPerNet($gameId, -4);
    }

    public function testARejectedLimitLeavesTheStoredSettingsAlone(): void
    {
        $gameId = $this->createGame();
        $this->gameManager->setPlayersPerNet($gameId, 6);

        try {
            $this->gameManager->setPlayersPerNet($gameId, 1);
        } catch (InvalidArgumentException) {
            // Swallowed on purpose; the assertion below is the point.
        }

        $this->assertSame(6, $this->gameRecord($gameId)->settings->playersPerNet);
    }

    public function testSetPlayersPerNetAcceptsTheMinimum(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->setPlayersPerNet($gameId, MinimumPlayersPerNetRule::MINIMUM);

        $this->assertSame(
            MinimumPlayersPerNetRule::MINIMUM,
            $this->gameRecord($gameId)->settings->playersPerNet,
        );
    }

    public function testSetPlayersPerNetRoundTripsThroughTheGameRecord(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->setPlayersPerNet($gameId, 4);

        $this->assertEquals(new GameSettings(playersPerNet: 4), $this->gameRecord($gameId)->settings);
    }

    public function testCreateGameStoresPlayersPerNetFromTitle(): void
    {
        $gameId = $this->gameManager->createGame(NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Bogatell 31.12.2099 18:00, 6 мест на сетку',
            'query_1',
        ));

        $this->assertSame(6, $this->gameRecord($gameId)->settings->playersPerNet);
    }

    public function testCreateGameWithBelowMinimumCountStoresNullWithoutThrowing(): void
    {
        $gameId = $this->gameManager->createGame(NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Bogatell 31.12.2099 18:00, 2 мест на сетку',
            'query_1',
        ));

        $this->assertNull($this->gameRecord($gameId)->settings->playersPerNet);
    }

    public function testChangeTitleIntoThePhraseSetsTheLimit(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->gameManager->changeTitle(
            $this->gameRecord($gameId),
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Beach Saturday 20:00, 6 spots per net',
        );

        $this->assertSame(6, $this->gameRecord($gameId)->settings->playersPerNet);
    }

    /** The load-bearing test for "the title is the sole source of truth". */
    public function testChangeTitleOutOfThePhraseClearsTheLimit(): void
    {
        $gameId = $this->gameManager->createGame(NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Beach 18:00, 6 spots per net',
            'query_1',
        ));

        $this->gameManager->changeTitle($this->gameRecord($gameId), new TelegramUser(id: 200, firstName: 'Danil'), 'Beach Saturday 20:00');

        $this->assertNull($this->gameRecord($gameId)->settings->playersPerNet);
    }

    public function testChangeTitleReplacesAnEarlierLimitFromTheTitle(): void
    {
        $gameId = $this->gameManager->createGame(NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Beach 18:00, 6 spots per net',
            'query_1',
        ));

        $this->gameManager->changeTitle(
            $this->gameRecord($gameId),
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Beach Saturday 20:00, 8 spots per net',
        );

        $this->assertSame(8, $this->gameRecord($gameId)->settings->playersPerNet);
    }

    /** No proposed time means changeTitle returns before touching anything, settings included. */
    public function testChangeTitleWithNoTimeLeavesSettingsAlone(): void
    {
        $gameId = $this->gameManager->createGame(NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Beach 18:00, 6 spots per net',
            'query_1',
        ));

        $this->gameManager->changeTitle(
            $this->gameRecord($gameId),
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Beach Saturday, 8 spots per net',
        );

        $this->assertSame(6, $this->gameRecord($gameId)->settings->playersPerNet);
    }

    public function testRecalculateGameTimeKeepsPlayersPerNetSettingAndPhrase(): void
    {
        $gameId = $this->gameManager->createGame(NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Danil'),
            'Bogatell 31.12.2099 18:00, 6 мест на сетку',
            'query_1',
        ));
        $this->seedUser($gameId, 201, position: 2, net: 1, time: '16:00');

        $this->gameManager->addNet($gameId, new TelegramUser(id: 201, firstName: 'Alice'));

        $game = $this->gameRecord($gameId);
        $this->assertSame('Bogatell 31.12.2099 16:00, 6 мест на сетку', $game->title);
        $this->assertSame(6, $game->settings->playersPerNet);
    }

    // --- joinWithTime ---

    public function testJoinWithTimeCreatesNewUserWithTime(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->setUserTime($gameId, new TelegramUser(id: 200, firstName: 'Danil'), '19:30');

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

        $this->gameManager->setUserTime($gameId, new TelegramUser(id: 200, firstName: 'Danil'), '20:00');

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

        $this->gameManager->addNet($gameId, new TelegramUser(id: 201, firstName: 'Alice'));

        $title = $this->gameRecord($gameId)->title;
        $this->assertSame('Beach 16:00', $title);
    }

    public function testRecalculatedGameTimeAlsoRewritesKickoff(): void
    {
        $gameId = $this->createGame(title: 'Beach 31.12.2099 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedUser($gameId, 201, position: 2, net: 0, time: '16:00');

        $this->gameManager->addNet($gameId, new TelegramUser(id: 201, firstName: 'Alice'));

        $game = $this->gameRecord($gameId);
        $this->assertSame('Beach 31.12.2099 16:00', $game->title);
        $this->assertSame('2099-12-31 15:00:00', Timestamp::format($game->kickoffAt));
    }

    public function testRemoveNetRecalculatesGameTimeToNextNetHolder(): void
    {
        $gameId = $this->createGame(title: 'Beach 16:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedUser($gameId, 201, position: 2, net: 1, time: '16:00');

        $this->gameManager->removeNet($gameId, 201);

        $title = $this->gameRecord($gameId)->title;
        $this->assertSame('Beach 18:00', $title);
    }

    public function testSetUserTimeRecalculatesGameTime(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');

        $this->gameManager->setUserTime($gameId, new TelegramUser(id: 200, firstName: 'Danil'), '15:30');

        $title = $this->gameRecord($gameId)->title;
        $this->assertSame('Beach 15:30', $title);
    }

    public function testRecalculateGameTimeIgnoresUsersWithoutNets(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedUser($gameId, 201, position: 2, net: 0, time: '15:00');

        $this->gameManager->addVolleyball($gameId, new TelegramUser(id: 201, firstName: 'Alice'));

        $title = $this->gameRecord($gameId)->title;
        $this->assertSame('Beach 18:00', $title);
    }

    public function testRecalculateGameTimeReplacesShortTimeFormatInTitle(): void
    {
        $gameId = $this->createGame(title: 'Beach 8:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '08:00');
        $this->seedUser($gameId, 201, position: 2, net: 0, time: '07:30');

        $this->gameManager->addNet($gameId, new TelegramUser(id: 201, firstName: 'Alice'));

        $title = $this->gameRecord($gameId)->title;
        $this->assertSame('Beach 07:30', $title);
    }

    public function testRemoveLastNetFallsBackToEarliestTimeAmongAllUsers(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedUser($gameId, 201, position: 2, net: 0, time: '16:00');

        $this->gameManager->removeNet($gameId, 200);

        $title = $this->gameRecord($gameId)->title;
        $this->assertSame('Beach 16:00', $title);
    }

    public function testRecalculateGameTimeKeepsTitleWhenNoChange(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00');
        $this->seedUser($gameId, 201, position: 2, net: 0, time: '18:00');

        $this->gameManager->addNet($gameId, new TelegramUser(id: 201, firstName: 'Alice'));

        $title = $this->gameRecord($gameId)->title;
        $this->assertSame('Beach 18:00', $title);
    }

    // --- changeTitle ---

    /** The caller hands in the game it already loaded, so only the time recalculation reads it back. */
    public function testChangeTitleDoesNotLoadTheGameItWasHandedIn(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());
        $gameRecord = $this->gameRecord($gameId);

        $queries = $this->queriesDuring(function () use ($gameRecord) {
            $this->gameManager->changeTitle($gameRecord, new TelegramUser(id: 200, firstName: 'Danil'), 'Beach Saturday 20:00');
        });

        $this->assertCount(1, $this->selectsAgainstGames($queries));
    }

    /** Title, kickoff, venue and settings land in one UPDATE — not a title write plus a separate settings write. */
    public function testChangeTitleWritesTitleAndSettingsInOneUpdate(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());
        $gameRecord = $this->gameRecord($gameId);

        $queries = $this->queriesDuring(function () use ($gameRecord) {
            $this->gameManager->changeTitle($gameRecord, new TelegramUser(id: 200, firstName: 'Danil'), 'Beach Saturday 20:00, 6 spots per net');
        });

        $this->assertCount(1, $this->updatesAgainstGames($queries));
        $this->assertSame(6, $this->gameRecord($gameId)->settings->playersPerNet);
    }

    public function testChangeTitleWhenCreatorIsOnlyUserUsesProposedTime(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->gameManager->changeTitle($this->gameRecord($gameId), new TelegramUser(id: 200, firstName: 'Danil'), 'Beach Saturday 20:00');

        $title = $this->gameRecord($gameId)->title;
        $this->assertSame('Beach Saturday 20:00', $title);
    }

    public function testChangeTitleRewritesKickoffAndVenue(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->gameManager->changeTitle($this->gameRecord($gameId), new TelegramUser(id: 200, firstName: 'Danil'), 'Bogatell 31.12.2099 20:00');

        $game = $this->gameRecord($gameId);
        $this->assertSame('2099-12-31 19:00:00', Timestamp::format($game->kickoffAt));
        $this->assertSame('Bogatell', $game->venueName);
    }

    public function testChangeTitleUpdatesCreatorUserTime(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->gameManager->changeTitle($this->gameRecord($gameId), new TelegramUser(id: 200, firstName: 'Danil'), 'Beach Saturday 20:00');

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame('20:00', $gameUser['time']);
    }

    public function testChangeTitlePreservesEarlierUserTimeInTitle(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00'); // creator
        $this->seedUser($gameId, 201, position: 2, net: 1, time: '16:00');

        $this->gameManager->changeTitle($this->gameRecord($gameId), new TelegramUser(id: 200, firstName: 'Danil'), 'Picnic Sunday 20:00');

        $title = $this->gameRecord($gameId)->title;
        $this->assertSame('Picnic Sunday 16:00', $title);
    }

    public function testChangeTitleLeavesOtherUsersTimesUnchanged(): void
    {
        $gameId = $this->createGame(title: 'Beach 18:00');
        $this->seedUser($gameId, 200, position: 1, net: 1, time: '18:00'); // creator
        $this->seedUser($gameId, 201, position: 2, net: 1, time: '16:00');

        $this->gameManager->changeTitle($this->gameRecord($gameId), new TelegramUser(id: 200, firstName: 'Danil'), 'Picnic Sunday 20:00');

        $creatorTime = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $otherTime = new GameUserRepository($this->db)->findByGameUser($gameId, 201);
        $this->assertSame('20:00', $creatorTime['time']);
        $this->assertSame('16:00', $otherTime['time']);
    }

    public function testChangeTitleNormalizesShortTimeFormat(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->gameManager->changeTitle($this->gameRecord($gameId), new TelegramUser(id: 200, firstName: 'Danil'), 'Beach Saturday 9:00');

        $title = $this->gameRecord($gameId)->title;
        $this->assertSame('Beach Saturday 09:00', $title);
    }

    public function testChangeTitleKeepsCreatorTimeWhenUnchanged(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->gameManager->changeTitle($this->gameRecord($gameId), new TelegramUser(id: 200, firstName: 'Danil'), 'Picnic Sunday 18:00');

        $title = $this->gameRecord($gameId)->title;
        $this->assertSame('Picnic Sunday 18:00', $title);
    }

    // --- GameReachedMinimumPlayers ---

    public function testCreateGameEnqueuesNoNotification(): void
    {
        $this->gameManager->createGame($this->newGameData());

        $this->assertSame([], $this->enqueuedNotifications());
    }

    public function testThreeSlotsEnqueueNoNotification(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->joinAs($gameId, 201, 202);

        $this->assertSame([], $this->enqueuedNotifications());
    }

    public function testFourthSlotNotifiesEveryoneExceptTheJoiner(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->joinAs($gameId, 201, 202, 203);

        $this->assertSame(
            [
                $this->minimumPlayersPayload($gameId, 200),
                $this->minimumPlayersPayload($gameId, 201),
                $this->minimumPlayersPayload($gameId, 202),
            ],
            $this->enqueuedNotifications(),
        );
    }

    public function testFifthSlotEnqueuesNothingMore(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());
        $this->joinAs($gameId, 201, 202, 203);
        SpyQueue::reset();

        $this->joinAs($gameId, 204);

        $this->assertSame([], $this->enqueuedNotifications());
    }

    public function testReachingTheMinimumAgainAfterDroppingBelowNotifiesAgain(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());
        $this->joinAs($gameId, 201, 202, 203);
        $this->gameManager->leaveGame($gameId, 203);
        SpyQueue::reset();

        $this->joinAs($gameId, 204);

        $this->assertSame(
            [
                $this->minimumPlayersPayload($gameId, 200),
                $this->minimumPlayersPayload($gameId, 201),
                $this->minimumPlayersPayload($gameId, 202),
            ],
            $this->enqueuedNotifications(),
        );
    }

    public function testPlusOneReachingTheMinimumNotifiesEachOtherUserOnce(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());

        $this->joinAs($gameId, 201, 201, 201);

        $this->assertSame([$this->minimumPlayersPayload($gameId, 200)], $this->enqueuedNotifications());
    }

    public function testAddNetThatJoinsTheFourthPlayerNotifies(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());
        $this->joinAs($gameId, 201, 202);

        $this->gameManager->addNet($gameId, new TelegramUser(id: 203, firstName: 'Player'));

        $this->assertCount(3, $this->enqueuedNotifications());
    }

    public function testAddNetByAPlayerAlreadyInAFourSlotGameEnqueuesNothing(): void
    {
        $gameId = $this->gameManager->createGame($this->newGameData());
        $this->joinAs($gameId, 201, 202, 203);
        SpyQueue::reset();

        $this->gameManager->addNet($gameId, new TelegramUser(id: 203, firstName: 'Player'));

        $this->assertSame([], $this->enqueuedNotifications());
    }

    // --- Helpers ---

    private function joinAs(int $gameId, int ...$telegramUserIds): void
    {
        foreach ($telegramUserIds as $telegramUserId) {
            $this->gameManager->joinGame($gameId, new TelegramUser(id: $telegramUserId, firstName: 'Player'));
        }
    }

    /** @return list<?array> */
    private function enqueuedNotifications(): array
    {
        return array_map(static fn(SpyQueue $queue): ?array => $queue->lastPayload, SpyQueue::$instances);
    }

    /** @return array{type: int, game_id: int, user_id: int} */
    private function minimumPlayersPayload(int $gameId, int $telegramUserId): array
    {
        return [
            'type' => NotificationType::GameReachedMinimumPlayers->value,
            'game_id' => $gameId,
            'user_id' => $telegramUserId,
        ];
    }

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

    /**
     * @param  string[] $queries
     * @return string[]
     */
    private function selectsAgainstGames(array $queries): array
    {
        return array_values(array_filter(
            $queries,
            static fn(string $query): bool => str_starts_with($query, 'SELECT') && str_contains($query, '"games"'),
        ));
    }

    /**
     * @param  string[] $queries
     * @return string[]
     */
    private function updatesAgainstGames(array $queries): array
    {
        return array_values(array_filter(
            $queries,
            static fn(string $query): bool => str_starts_with($query, 'UPDATE') && str_contains($query, '"games"'),
        ));
    }

    private function gameRecord(int $gameId): GameRecord
    {
        return $this->gameManager->findGameRecordById($gameId);
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
