<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Workers;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Notifications\NotificationEnqueuer;
use BeachVolleybot\Notifications\ShortOfPlayersNotifier;
use BeachVolleybot\Tests\Fixtures\ReadsEnqueuedNotifications;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\Workers\NotificationScanWorker;
use DateTimeImmutable;
use DateTimeZone;

final class NotificationScanWorkerTest extends DatabaseTestCase
{
    use ReadsEnqueuedNotifications;

    public function testOneTickWarnsAShortGameWhoseMarkPassedSinceTheLastScan(): void
    {
        $gameId = $this->createShortGameWhoseMarkPassed('30 minutes ago');

        $this->runOneTick(startedAt: new DateTimeImmutable('-1 hour'));

        $this->assertSame([200], $this->notifiedUserIds(NotificationType::GameShortBeforeKickoff));
        $this->assertSame([$gameId], array_column($this->enqueuedNotifications(), 'game_id'));
    }

    public function testAFreshWorkerDoesNotCatchUpOnMarksThatPassedBeforeItStarted(): void
    {
        $this->createShortGameWhoseMarkPassed('30 minutes ago');

        $this->runOneTick(startedAt: new DateTimeImmutable());

        $this->assertSame([], $this->enqueuedNotifications());
    }

    // --- Helpers ---

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        SpyQueue::reset();
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    private function createShortGameWhoseMarkPassed(string $markPassed): int
    {
        $kickoffAt = new DateTimeImmutable($markPassed, new DateTimeZone('UTC'))
            ->modify('+' . ShortOfPlayersNotifier::LEAD_TIME_HOURS . ' hours');
        $gameId = $this->createGame(kickoffAt: $kickoffAt->format('Y-m-d H:i:s'));
        $this->createGameUser($gameId, 200);

        return $gameId;
    }

    private function runOneTick(DateTimeImmutable $startedAt): void
    {
        $notifier = new ShortOfPlayersNotifier(new NotificationEnqueuer(SpyQueue::class, sys_get_temp_dir()));

        new NotificationScanWorker(maxTicks: 1, startedAt: $startedAt, shortOfPlayersNotifier: $notifier)->run();
    }
}
