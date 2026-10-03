<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Notifications;

use BeachVolleybot\Notifications\NotificationEnqueuer;
use BeachVolleybot\Notifications\NotificationQueuePayload;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use BeachVolleybot\User\NotificationType;
use DanilKashin\FileQueue\Queue\FileQueue;
use PHPUnit\Framework\TestCase;

final class NotificationEnqueuerTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        SpyQueue::reset();
        $this->baseDir = sys_get_temp_dir() . '/bvb_notifications_' . uniqid('', true);
        mkdir($this->baseDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->baseDir . '/*') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($this->baseDir);
    }

    public function testEnqueuesOntoTheGamesQueue(): void
    {
        new NotificationEnqueuer(SpyQueue::class, $this->baseDir)
            ->enqueue(new NotificationQueuePayload(NotificationType::PromotedIntoGame, 12, 200));

        $this->assertCount(1, SpyQueue::$instances);
        $this->assertSame('notification_12', SpyQueue::$instances[0]->queueName);
        $this->assertSame($this->baseDir, SpyQueue::$instances[0]->baseDir);
        $this->assertSame(['type' => 3, 'game_id' => 12, 'user_id' => 200], SpyQueue::$instances[0]->lastPayload);
    }

    public function testEveryRecipientOfOneGameSharesItsQueue(): void
    {
        $enqueuer = new NotificationEnqueuer(SpyQueue::class, $this->baseDir);

        $enqueuer->enqueue(new NotificationQueuePayload(NotificationType::GameShortBeforeKickoff, 12, 200));
        $enqueuer->enqueue(new NotificationQueuePayload(NotificationType::GameShortBeforeKickoff, 12, 201));

        $this->assertSame(
            ['notification_12', 'notification_12'],
            array_map(static fn(SpyQueue $queue): string => $queue->queueName, SpyQueue::$instances),
        );
        $this->assertSame(['type' => 2, 'game_id' => 12, 'user_id' => 201], SpyQueue::$instances[1]->lastPayload);
    }

    public function testEnqueuesOnePayloadPerUser(): void
    {
        new NotificationEnqueuer(SpyQueue::class, $this->baseDir)
            ->enqueueForUsers(NotificationType::KickoffTimeChanged, 12, [200, 201]);

        $this->assertSame(
            [
                ['type' => 5, 'game_id' => 12, 'user_id' => 200],
                ['type' => 5, 'game_id' => 12, 'user_id' => 201],
            ],
            array_map(static fn(SpyQueue $queue): array => $queue->lastPayload, SpyQueue::$instances),
        );
    }

    public function testEnqueuesNothingForNoUsers(): void
    {
        new NotificationEnqueuer(SpyQueue::class, $this->baseDir)
            ->enqueueForUsers(NotificationType::KickoffTimeChanged, 12, []);

        $this->assertEmpty(SpyQueue::$instances);
    }

    public function testWritesADequeuablePayload(): void
    {
        new NotificationEnqueuer(FileQueue::class, $this->baseDir)
            ->enqueue(new NotificationQueuePayload(NotificationType::GameReachedMinimumPlayers, 7, 200));

        $message = new FileQueue('notification_7', $this->baseDir)->dequeue();
        $this->assertNotNull($message);
        $this->assertEquals(
            new NotificationQueuePayload(NotificationType::GameReachedMinimumPlayers, 7, 200),
            NotificationQueuePayload::fromArray($message->payload),
        );
    }

    public function testDefaultsToAQueuesSubdirectoryTheAppWorkerDoesNotScan(): void
    {
        // AppQueueWorker globs BASE_QUEUE_DIR/*.queue.data; a subdirectory keeps notification payloads out of it.
        $this->assertSame(BASE_QUEUE_DIR . '/notifications', NotificationEnqueuer::QUEUE_DIR);
    }

    public function testEachGameGetsItsOwnQueue(): void
    {
        $enqueuer = new NotificationEnqueuer(SpyQueue::class, $this->baseDir);

        $enqueuer->enqueue(new NotificationQueuePayload(NotificationType::GameShortBeforeKickoff, 12, 200));
        $enqueuer->enqueue(new NotificationQueuePayload(NotificationType::GameShortBeforeKickoff, 13, 200));

        $this->assertSame(
            ['notification_12', 'notification_13'],
            array_map(static fn(SpyQueue $queue): string => $queue->queueName, SpyQueue::$instances),
        );
    }

    public function testKeepsOneGamesNotificationsInOrder(): void
    {
        $enqueuer = new NotificationEnqueuer(FileQueue::class, $this->baseDir);
        $first = new NotificationQueuePayload(NotificationType::BumpedFromGame, 7, 200);
        $second = new NotificationQueuePayload(NotificationType::PromotedIntoGame, 7, 200);

        $enqueuer->enqueue($first);
        $enqueuer->enqueue($second);

        $queue = new FileQueue('notification_7', $this->baseDir);
        $this->assertEquals($first, NotificationQueuePayload::fromArray($queue->dequeue()->payload));
        $this->assertEquals($second, NotificationQueuePayload::fromArray($queue->dequeue()->payload));
        $this->assertNull($queue->dequeue());
    }
}
