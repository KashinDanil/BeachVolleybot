<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Notifications;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\ParsedTitle;
use BeachVolleybot\Notifications\KickoffChangeNotifier;
use BeachVolleybot\Notifications\NotificationEnqueuer;
use BeachVolleybot\Tests\Fixtures\ReadsEnqueuedNotifications;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use BeachVolleybot\User\NotificationType;
use DateTimeImmutable;
use DateTimeZone;

final class KickoffChangeNotifierTest extends DatabaseTestCase
{
    use ReadsEnqueuedNotifications;

    private KickoffChangeNotifier $notifier;

    public function testMovedKickoffNotifiesEveryoneExceptTheActor(): void
    {
        $game = $this->createGameWithPlayers(200, 201, 202);

        $this->notifier->notifyIfChanged($game, $game->kickoffAt->modify('+1 hour'), 201);

        $this->assertSame([200, 202], $this->notifiedUserIds(NotificationType::KickoffTimeChanged));
        $this->assertSame([$game->gameId], array_unique(array_column($this->enqueuedNotifications(), 'game_id')));
    }

    public function testHalfAnHourLaterNotifies(): void
    {
        $game = $this->createGameWithPlayers(200, 201);

        $this->notifier->notifyIfChanged($game, $game->kickoffAt->modify('+30 minutes'), 201);

        $this->assertSame([200], $this->notifiedUserIds(NotificationType::KickoffTimeChanged));
    }

    public function testSameTimeOnAnotherDayNotifies(): void
    {
        $game = $this->createGameWithPlayers(200, 201);

        $this->notifier->notifyIfChanged($game, $game->kickoffAt->modify('-1 day'), 201);

        $this->assertSame([200], $this->notifiedUserIds(NotificationType::KickoffTimeChanged));
    }

    /** The stored kickoff comes back in the venue's timezone, the parsed one is built there too. */
    public function testStoredKickoffMatchesTheSameTitleParsedAgain(): void
    {
        $game = $this->createGameWithPlayers(200, 201);

        $this->notifier->notifyIfChanged($game, ParsedTitle::parse($game->title, $game->createdAt)->kickoffAt, 201);

        $this->assertSame([], $this->enqueuedNotifications());
    }

    public function testSameInstantInUtcSendsNothing(): void
    {
        $game = $this->createGameWithPlayers(200, 201);

        $this->notifier->notifyIfChanged($game, $game->kickoffAt->setTimezone(new DateTimeZone('UTC')), 201);

        $this->assertSame([], $this->enqueuedNotifications());
    }

    public function testSameInstantInAFarAwayTimezoneSendsNothing(): void
    {
        $game = $this->createGameWithPlayers(200, 201);

        $this->notifier->notifyIfChanged($game, $game->kickoffAt->setTimezone(new DateTimeZone('Asia/Tokyo')), 201);

        $this->assertSame([], $this->enqueuedNotifications());
    }

    /** 18:00 in Tokyo is another moment than 18:00 in Barcelona, so it is a change. */
    public function testSameWallClockInAnotherTimezoneNotifies(): void
    {
        $game = $this->createGameWithPlayers(200, 201);
        $sameWallClockInTokyo = new DateTimeImmutable($game->kickoffAt->format('Y-m-d H:i'), new DateTimeZone('Asia/Tokyo'));

        $this->notifier->notifyIfChanged($game, $sameWallClockInTokyo, 201);

        $this->assertSame([200], $this->notifiedUserIds(NotificationType::KickoffTimeChanged));
    }

    public function testActorAloneInTheGameSendsNothing(): void
    {
        $game = $this->createGameWithPlayers(200);

        $this->notifier->notifyIfChanged($game, $game->kickoffAt->modify('+1 day'), 200);

        $this->assertSame([], $this->enqueuedNotifications());
    }

    // --- Helpers ---

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        SpyQueue::reset();
        $this->notifier = new KickoffChangeNotifier(new NotificationEnqueuer(SpyQueue::class, sys_get_temp_dir()));
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    private function createGameWithPlayers(int ...$telegramUserIds): GameRecord
    {
        $gameId = $this->createGame(title: 'Beach 31.12.2099 18:00');

        foreach ($telegramUserIds as $telegramUserId) {
            $this->createGameUser($gameId, $telegramUserId);
        }

        return new GameManager()->findGameRecordById($gameId);
    }
}
