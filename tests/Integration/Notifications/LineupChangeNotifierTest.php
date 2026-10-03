<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Notifications;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\GameSlotManager;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\Notifications\LineupChangeNotifier;
use BeachVolleybot\Notifications\NotificationEnqueuer;
use BeachVolleybot\Tests\Fixtures\ReadsEnqueuedNotifications;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use BeachVolleybot\User\NotificationType;

final class LineupChangeNotifierTest extends DatabaseTestCase
{
    use ReadsEnqueuedNotifications;

    private LineupChangeNotifier $notifier;

    private GameUserManager $gameUserManager;

    // --- capture ---

    public function testCaptureWithoutPlayersPerNetReadsNoRoster(): void
    {
        $gameId = $this->createGameWithPlayers(null, 6);

        $queries = $this->queriesDuring(function () use ($gameId) {
            $this->assertNull($this->notifier->capture($this->gameRecord($gameId))->playingUserIds);
        });

        $this->assertSame([], $this->rosterReads($queries));
    }

    public function testCaptureWithoutACourtHasNoDivider(): void
    {
        $gameId = $this->createGameWithPlayers(4, 6, ballsOfTheFirstPlayer: 0);

        $this->assertNull($this->notifier->capture($this->gameRecord($gameId))->playingUserIds);
    }

    public function testCaptureListsThePlayersAboveTheDivider(): void
    {
        $gameId = $this->createGameWithPlayers(4, 6);

        $this->assertSame([200, 201, 202, 203], $this->notifier->capture($this->gameRecord($gameId))->playingUserIds);
    }

    // --- notifyChanges: roster changes ---

    public function testPlayerLeavingPromotesTheFirstReserve(): void
    {
        $gameId = $this->createGameWithPlayers(4, 6);
        $snapshot = $this->notifier->capture($this->gameRecord($gameId));

        new GameSlotManager()->deleteSlot($gameId, 2);
        $this->gameUserManager->deleteGameUser($gameId, 201);
        $this->notifier->notifyChanges($snapshot, 201);

        $this->assertSame([204], $this->notifiedUserIds(NotificationType::PromotedIntoGame));
        $this->assertSame([], $this->notifiedUserIds(NotificationType::BumpedFromGame));
    }

    public function testShrinkingLimitBumpsThePlayersPastIt(): void
    {
        $gameId = $this->createGameWithPlayers(4, 9);
        $this->giveNetAndBall($gameId, 201);
        $snapshot = $this->notifier->capture($this->gameRecord($gameId));

        $this->gameUserManager->decrementNet($gameId, 201);
        $this->notifier->notifyChanges($snapshot, 201);

        $this->assertSame([204, 205, 206, 207], $this->notifiedUserIds(NotificationType::BumpedFromGame));
        $this->assertSame([], $this->notifiedUserIds(NotificationType::PromotedIntoGame));
    }

    public function testGrowingLimitPromotesTheReserves(): void
    {
        $gameId = $this->createGameWithPlayers(4, 7);
        $this->gameUserManager->incrementNet($gameId, 201);
        $snapshot = $this->notifier->capture($this->gameRecord($gameId));

        $this->gameUserManager->incrementVolleyball($gameId, 201);
        $this->notifier->notifyChanges($snapshot, 201);

        $this->assertSame([204, 205, 206], $this->notifiedUserIds(NotificationType::PromotedIntoGame));
    }

    /** The ball moves from a player to a reserve, who comes up as a carrier and pushes the last player out. */
    public function testOneChangeCanPromoteAndBumpAtOnce(): void
    {
        $gameId = $this->createGameWithPlayers(4, 5, ballsOfTheFirstPlayer: 0);
        $this->gameUserManager->incrementVolleyball($gameId, 201);
        $snapshot = $this->notifier->capture($this->gameRecord($gameId));

        $this->gameUserManager->decrementVolleyball($gameId, 201);
        $this->gameUserManager->incrementVolleyball($gameId, 204);
        $this->notifier->notifyChanges($snapshot, 201);

        $this->assertSame([204], $this->notifiedUserIds(NotificationType::PromotedIntoGame));
        $this->assertSame([203], $this->notifiedUserIds(NotificationType::BumpedFromGame));
    }

    public function testTheActorIsNeverNotified(): void
    {
        $gameId = $this->createGameWithPlayers(4, 9);
        $this->giveNetAndBall($gameId, 205);
        $snapshot = $this->notifier->capture($this->gameRecord($gameId));

        $this->gameUserManager->decrementVolleyball($gameId, 205);
        $this->notifier->notifyChanges($snapshot, 205);

        $this->assertSame([204, 206, 207], $this->notifiedUserIds(NotificationType::BumpedFromGame));
    }

    public function testChangeThatMovesNobodySendsNothing(): void
    {
        $gameId = $this->createGameWithPlayers(4, 6);
        $snapshot = $this->notifier->capture($this->gameRecord($gameId));

        $this->gameUserManager->incrementVolleyball($gameId, 205);
        $this->notifier->notifyChanges($snapshot, 205);

        $this->assertSame([], SpyQueue::$instances);
    }

    public function testLimitAppearingWithTheFirstCourtSendsNothing(): void
    {
        $gameId = $this->createGameWithPlayers(4, 6, ballsOfTheFirstPlayer: 0);
        $snapshot = $this->notifier->capture($this->gameRecord($gameId));

        $this->gameUserManager->incrementVolleyball($gameId, 200);
        $this->notifier->notifyChanges($snapshot, 200);

        $this->assertSame([], SpyQueue::$instances);
    }

    public function testLimitDisappearingWithTheLastCourtSendsNothing(): void
    {
        $gameId = $this->createGameWithPlayers(4, 6);
        $snapshot = $this->notifier->capture($this->gameRecord($gameId));

        $this->gameUserManager->decrementNet($gameId, 200);
        $this->notifier->notifyChanges($snapshot, 200);

        $this->assertSame([], SpyQueue::$instances);
    }

    public function testGameWithoutPlayersPerNetSendsNothingAndReadsNoRoster(): void
    {
        $gameId = $this->createGameWithPlayers(null, 6);
        $snapshot = $this->notifier->capture($this->gameRecord($gameId));
        new GameSlotManager()->deleteSlot($gameId, 2);

        $queries = $this->queriesDuring(fn() => $this->notifier->notifyChanges($snapshot, 201));

        $this->assertSame([], $this->rosterReads($queries));
        $this->assertSame([], SpyQueue::$instances);
    }

    // --- notifyPlayersPerNetChange ---

    public function testSameCountSendsNothingAndReadsNoRoster(): void
    {
        $gameId = $this->createGameWithPlayers(4, 6);

        $queries = $this->queriesDuring(
            fn() => $this->notifier->notifyPlayersPerNetChange($this->gameRecord($gameId), new GameSettings(playersPerNet: 4), 200),
        );

        $this->assertSame([], $this->rosterReads($queries));
        $this->assertSame([], SpyQueue::$instances);
    }

    public function testSettingACountBumpsThePlayersPastIt(): void
    {
        $gameId = $this->createGameWithPlayers(null, 6);

        $this->notifier->notifyPlayersPerNetChange($this->gameRecord($gameId), new GameSettings(playersPerNet: 4), 200);

        $this->assertSame([204, 205], $this->notifiedUserIds(NotificationType::BumpedFromGame));
        $this->assertSame([], $this->notifiedUserIds(NotificationType::PromotedIntoGame));
    }

    public function testClearingTheCountPromotesTheReserves(): void
    {
        $gameId = $this->createGameWithPlayers(4, 6);

        $this->notifier->notifyPlayersPerNetChange($this->gameRecord($gameId), new GameSettings(), 200);

        $this->assertSame([204, 205], $this->notifiedUserIds(NotificationType::PromotedIntoGame));
        $this->assertSame([], $this->notifiedUserIds(NotificationType::BumpedFromGame));
    }

    public function testRaisingTheCountPromotesTheReserves(): void
    {
        $gameId = $this->createGameWithPlayers(4, 6);

        $this->notifier->notifyPlayersPerNetChange($this->gameRecord($gameId), new GameSettings(playersPerNet: 5), 200);

        $this->assertSame([204], $this->notifiedUserIds(NotificationType::PromotedIntoGame));
    }

    public function testLoweringTheCountBumpsThePlayersPastIt(): void
    {
        $gameId = $this->createGameWithPlayers(5, 6);

        $this->notifier->notifyPlayersPerNetChange($this->gameRecord($gameId), new GameSettings(playersPerNet: 4), 200);

        $this->assertSame([204], $this->notifiedUserIds(NotificationType::BumpedFromGame));
    }

    public function testChangingTheCountWithoutACourtSendsNothing(): void
    {
        $gameId = $this->createGameWithPlayers(null, 6, ballsOfTheFirstPlayer: 0);

        $this->notifier->notifyPlayersPerNetChange($this->gameRecord($gameId), new GameSettings(playersPerNet: 4), 200);

        $this->assertSame([], SpyQueue::$instances);
    }

    public function testCountChangeSkipsTheActor(): void
    {
        $gameId = $this->createGameWithPlayers(null, 6);

        $this->notifier->notifyPlayersPerNetChange($this->gameRecord($gameId), new GameSettings(playersPerNet: 4), 204);

        $this->assertSame([205], $this->notifiedUserIds(NotificationType::BumpedFromGame));
    }

    // --- Helpers ---

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        SpyQueue::reset();
        $this->notifier = new LineupChangeNotifier(new NotificationEnqueuer(SpyQueue::class, sys_get_temp_dir()));
        $this->gameUserManager = new GameUserManager();
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    /** Players 200, 201, … sign up in order; 200 brings the only net and, unless told otherwise, the only ball. */
    private function createGameWithPlayers(?int $playersPerNet, int $playerCount, int $ballsOfTheFirstPlayer = 1): int
    {
        $gameId = $this->createGame();
        $this->setGameSettings($gameId, new GameSettings(playersPerNet: $playersPerNet));

        for ($position = 1; $position <= $playerCount; $position++) {
            $this->seedPlayer($gameId, 199 + $position, $position);
        }

        $this->gameUserManager->incrementNet($gameId, 200);

        for ($ball = 0; $ball < $ballsOfTheFirstPlayer; $ball++) {
            $this->gameUserManager->incrementVolleyball($gameId, 200);
        }

        return $gameId;
    }

    private function seedPlayer(int $gameId, int $telegramUserId, int $position): void
    {
        $this->createGameUser($gameId, $telegramUserId);
        $this->db->insert('game_slots', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'position' => $position,
        ]);
    }

    private function giveNetAndBall(int $gameId, int $telegramUserId): void
    {
        $this->gameUserManager->incrementNet($gameId, $telegramUserId);
        $this->gameUserManager->incrementVolleyball($gameId, $telegramUserId);
    }

    private function gameRecord(int $gameId): GameRecord
    {
        return new GameManager()->findGameRecordById($gameId);
    }


    /**
     * @param list<string> $queries
     *
     * @return list<string>
     */
    private function rosterReads(array $queries): array
    {
        return array_values(array_filter(
            $queries,
            static fn(string $query): bool => str_contains($query, 'SELECT * FROM "game_slots"'),
        ));
    }
}
