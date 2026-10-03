<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Notifications;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\GameSlotManager;
use BeachVolleybot\Notifications\NotificationEnqueuer;
use BeachVolleybot\Notifications\ShortOfPlayersNotifier;
use BeachVolleybot\Tests\Fixtures\ReadsEnqueuedNotifications;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use BeachVolleybot\User\NotificationType;
use DateTimeImmutable;
use DateTimeZone;

final class ShortOfPlayersNotifierTest extends DatabaseTestCase
{
    use ReadsEnqueuedNotifications;

    private const string KICKOFF_AT = '2099-12-31 18:00:00';

    private ShortOfPlayersNotifier $notifier;

    public function testShortGameWhoseMarkFallsInTheWindowNotifiesEveryoneInIt(): void
    {
        $gameId = $this->createGameWithSlots([200, 201, 202]);

        $this->notifier->notifyCrossedBetween($this->mark()->modify('-15 minutes'), $this->mark()->modify('+15 minutes'));

        $this->assertSame([200, 201, 202], $this->notifiedUserIds(NotificationType::GameShortBeforeKickoff));
        $this->assertSame([$gameId], array_unique(array_column($this->enqueuedNotifications(), 'game_id')));
    }

    public function testGameWithTheMinimumSendsNothing(): void
    {
        $this->createGameWithSlots([200, 201, 202, 203]);

        $this->notifier->notifyCrossedBetween($this->mark()->modify('-15 minutes'), $this->mark()->modify('+15 minutes'));

        $this->assertSame([], $this->enqueuedNotifications());
    }

    public function testPlusOnesCountTowardTheMinimum(): void
    {
        $this->createGameWithSlots([200, 200, 201, 202]);

        $this->notifier->notifyCrossedBetween($this->mark()->modify('-15 minutes'), $this->mark()->modify('+15 minutes'));

        $this->assertSame([], $this->enqueuedNotifications());
    }

    public function testPlusOneOfAShortGameIsNotifiedOnce(): void
    {
        $this->createGameWithSlots([200, 200, 201]);

        $this->notifier->notifyCrossedBetween($this->mark()->modify('-15 minutes'), $this->mark()->modify('+15 minutes'));

        $this->assertSame([200, 201], $this->notifiedUserIds(NotificationType::GameShortBeforeKickoff));
    }

    public function testMarkOnTheWindowStartIsIncluded(): void
    {
        $this->createGameWithSlots([200]);

        $this->notifier->notifyCrossedBetween($this->mark(), $this->mark()->modify('+30 minutes'));

        $this->assertSame([200], $this->notifiedUserIds(NotificationType::GameShortBeforeKickoff));
    }

    /** The next window starts there, so including it here would warn the game twice. */
    public function testMarkOnTheWindowEndIsLeftForTheNextWindow(): void
    {
        $this->createGameWithSlots([200]);

        $this->notifier->notifyCrossedBetween($this->mark()->modify('-30 minutes'), $this->mark());

        $this->assertSame([], $this->enqueuedNotifications());
    }

    public function testMarkOutsideTheWindowSendsNothing(): void
    {
        $this->createGameWithSlots([200]);

        $this->notifier->notifyCrossedBetween($this->mark()->modify('+1 minute'), $this->mark()->modify('+31 minutes'));
        $this->notifier->notifyCrossedBetween($this->mark()->modify('-31 minutes'), $this->mark()->modify('-1 minute'));

        $this->assertSame([], $this->enqueuedNotifications());
    }

    // --- Helpers ---

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        SpyQueue::reset();
        $this->notifier = new ShortOfPlayersNotifier(new NotificationEnqueuer(SpyQueue::class, sys_get_temp_dir()));
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    private function mark(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::KICKOFF_AT, new DateTimeZone('UTC'))
            ->modify('-' . ShortOfPlayersNotifier::LEAD_TIME_HOURS . ' hours');
    }

    /** @param list<int> $slotOwnerIds one entry per slot, so a repeated id is a +1 */
    private function createGameWithSlots(array $slotOwnerIds): int
    {
        $gameId = $this->createGame(title: 'Beach 31.12.2099 18:00', kickoffAt: self::KICKOFF_AT);
        $gameSlotManager = new GameSlotManager();

        foreach (array_unique($slotOwnerIds) as $telegramUserId) {
            $this->createGameUser($gameId, $telegramUserId);
        }

        foreach ($slotOwnerIds as $telegramUserId) {
            $gameSlotManager->addSlot($gameId, $telegramUserId);
        }

        return $gameId;
    }
}
