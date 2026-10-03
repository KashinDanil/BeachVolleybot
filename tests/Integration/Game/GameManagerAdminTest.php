<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\AdminGameManager;
use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\Notifications\KickoffChangeNotifier;
use BeachVolleybot\Notifications\LineupChangeNotifier;
use BeachVolleybot\Notifications\MinimumPlayersNotifier;
use BeachVolleybot\Notifications\NotificationEnqueuer;
use BeachVolleybot\Tests\Fixtures\ReadsEnqueuedNotifications;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use BeachVolleybot\User\NotificationType;

final class GameManagerAdminTest extends DatabaseTestCase
{
    use ReadsEnqueuedNotifications;

    private GameManager $gameManager;

    private AdminGameManager $adminGameManager;

    public function testIncrementNetAddsNet(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);

        $result = $this->adminGameManager->adminAddNet($gameId, 200);

        $this->assertSame(EquipmentResult::Added, $result);
        $gameUser = new GameUserManager()->findGameUserRecord($gameId, 200);
        $this->assertSame(1, $gameUser->net);
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
        $gameUser = new GameUserManager()->findGameUserRecord($gameId, 200);
        $this->assertSame(1, $gameUser->volleyball);
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

    // --- incrementNet: multiple increments ---

    public function testIncrementNetMultipleTimesAccumulates(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);

        $this->adminGameManager->adminAddNet($gameId, 200);
        $this->adminGameManager->adminAddNet($gameId, 200);

        $gameUser = new GameUserManager()->findGameUserRecord($gameId, 200);
        $this->assertSame(2, $gameUser->net);
    }

    // --- incrementVolleyball: multiple increments ---

    public function testIncrementVolleyballMultipleTimesAccumulates(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);

        $this->adminGameManager->adminAddVolleyball($gameId, 200);
        $this->adminGameManager->adminAddVolleyball($gameId, 200);
        $this->adminGameManager->adminAddVolleyball($gameId, 200);

        $gameUser = new GameUserManager()->findGameUserRecord($gameId, 200);
        $this->assertSame(3, $gameUser->volleyball);
    }

    // --- equipment: game time ---

    public function testAdminAddNetRecalculatesGameTimeToTheTargetUser(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);
        new GameUserManager()->incrementNet($gameId, 200);
        $this->seedGameUser($gameId, 201, 2, time: '16:00');

        $this->adminGameManager->adminAddNet($gameId, 201);

        $this->assertSame('Test 16:00', $this->db->get('games', 'title', ['game_id' => $gameId]));
        $this->assertSame([200], $this->notifiedUserIds(NotificationType::KickoffTimeChanged));
    }

    public function testAdminAddVolleyballRecalculatesGameTimeToTheTargetUser(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);
        new GameUserManager()->incrementNet($gameId, 200);
        $this->seedGameUser($gameId, 201, 2, time: '16:00');

        $this->adminGameManager->adminAddVolleyball($gameId, 201);

        $this->assertSame('Test 16:00', $this->db->get('games', 'title', ['game_id' => $gameId]));
        $this->assertSame([200], $this->notifiedUserIds(NotificationType::KickoffTimeChanged));
    }

    // --- setLocation: from coordinates ---

    public function testSetLocationSetsValueFromCoordinates(): void
    {
        $gameId = $this->createGame();

        $this->gameManager->setLocation($gameId, 55.7, 37.6);

        $game = $this->db->get('games', '*', ['game_id' => $gameId]);
        $this->assertSame('55.7,37.6', $game['location']);
    }

    // --- adminAddSlot: GameReachedMinimumPlayers ---

    public function testAdminAddSlotReachingTheMinimumNotifiesEveryoneExceptTheSlotOwner(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);
        $this->addSlot($gameId, 200, 2);
        $this->seedGameUser($gameId, 201, 3);

        $this->adminGameManager->adminAddSlot($gameId, 200);

        $this->assertSame(
            [['type' => NotificationType::GameReachedMinimumPlayers->value, 'game_id' => $gameId, 'user_id' => 201]],
            $this->enqueuedNotifications(),
        );
    }

    public function testAdminAddSlotBelowTheMinimumEnqueuesNothing(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);

        $this->adminGameManager->adminAddSlot($gameId, 200);

        $this->assertSame([], SpyQueue::$instances);
    }

    // --- adminAddVolleyball: PromotedIntoGame ---

    public function testAdminCompletingASecondCourtPromotesReservesButNotTheTargetUser(): void
    {
        $gameId = $this->createGameWithUserSlot(200, 1);
        $gameUserManager = new GameUserManager();
        $gameUserManager->incrementNet($gameId, 200);
        $gameUserManager->incrementVolleyball($gameId, 200);
        $this->setGameSettings($gameId, new GameSettings(playersPerNet: 4));

        foreach ([201, 202, 203, 204, 205] as $index => $telegramUserId) {
            $this->seedGameUser($gameId, $telegramUserId, $index + 2);
        }

        $this->adminGameManager->adminAddNet($gameId, 205);
        SpyQueue::reset();

        $this->adminGameManager->adminAddVolleyball($gameId, 205);

        $this->assertSame(
            [['type' => NotificationType::PromotedIntoGame->value, 'game_id' => $gameId, 'user_id' => 204]],
            $this->enqueuedNotifications(),
        );
    }

    private function seedGameUser(int $gameId, int $telegramUserId, int $position, string $time = '18:00'): void
    {
        $this->createUser($telegramUserId);
        $this->db->insert('game_users', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'time' => $time,
        ]);
        $this->addSlot($gameId, $telegramUserId, $position);
    }

    // --- helpers ---

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        $this->gameManager = new GameManager();
        SpyQueue::reset();
        $spyEnqueuer = new NotificationEnqueuer(SpyQueue::class, sys_get_temp_dir());
        $this->adminGameManager = new AdminGameManager(
            new MinimumPlayersNotifier($spyEnqueuer),
            new LineupChangeNotifier($spyEnqueuer),
            new KickoffChangeNotifier($spyEnqueuer),
        );
    }

    protected function tearDown(): void
    {
        Connection::close();
    }
}
