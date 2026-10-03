<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Notifications\NotificationEnqueuer;
use BeachVolleybot\Notifications\NotificationQueuePayload;
use BeachVolleybot\Notifications\NotificationSender;
use BeachVolleybot\Processors\NotificationQueueProcessor;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\Workers\NotificationQueueWorker;
use DanilKashin\FileQueue\Queue\FileQueue;
use DanilKashin\FileQueue\Queue\QueueMessage;

final class NotificationQueueWorkerTest extends ProcessorTestCase
{
    /** The worker prints '+' per processed message and '.' per idle tick; '-' would be a failed one. */
    private const string NO_FAILED_MESSAGE = '/\A[+.]+\z/';

    private string $queuesDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queuesDir = sys_get_temp_dir() . '/bvb_notification_worker_' . uniqid('', true);
        mkdir($this->queuesDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->queuesDir . '/*') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($this->queuesDir);
        parent::tearDown();
    }

    public function testDrainsAQueuedNotificationIntoADirectMessage(): void
    {
        $gameId = $this->createGame('Bogatell 31.12.2099 18:00');
        $this->createGameUser($gameId, 200);
        $this->db->update(
            'users',
            ['notifications' => new NotificationSettings()->enable(NotificationType::BumpedFromGame)->toInt()],
            ['telegram_user_id' => 200],
        );
        new NotificationEnqueuer(FileQueue::class, $this->queuesDir)
            ->enqueue(new NotificationQueuePayload(NotificationType::BumpedFromGame, $gameId, 200));

        $this->expectOutputString('+'); // FileQueueWorker marks each processed message on stdout
        $this->createWorker()->run();

        $sendMessageCalls = array_values(array_filter($this->bot->calls, static fn(array $call): bool => 'sendMessage' === $call['method']));
        $this->assertCount(1, $sendMessageCalls);
        $this->assertSame(200, $sendMessageCalls[0]['args'][0]);
        $this->assertTrue(new FileQueue('notification_' . $gameId, $this->queuesDir)->isEmpty());
    }

    public function testDrainsTheQueuesOfSeveralGames(): void
    {
        $firstGameId = $this->createGame('Bogatell 31.12.2099 18:00');
        $secondGameId = $this->createGame('Bogatell 30.12.2099 18:00', inlineMessageId: 'msg_2', gameKey: 'query_2');
        $this->joinGameWithOptIn($firstGameId, 200, NotificationType::GameShortBeforeKickoff);
        $this->joinGameWithOptIn($secondGameId, 201, NotificationType::GameShortBeforeKickoff);
        $enqueuer = new NotificationEnqueuer(FileQueue::class, $this->queuesDir);
        $enqueuer->enqueue(new NotificationQueuePayload(NotificationType::GameShortBeforeKickoff, $firstGameId, 200));
        $enqueuer->enqueue(new NotificationQueuePayload(NotificationType::GameShortBeforeKickoff, $secondGameId, 201));

        $this->expectOutputRegex(self::NO_FAILED_MESSAGE);
        $this->createWorker(maxTicks: 2)->run();

        $chatIds = $this->getSentChatIds();
        sort($chatIds);
        $this->assertSame([200, 201], $chatIds);
    }

    public function testABadPayloadDoesNotStopTheNextNotification(): void
    {
        $gameId = $this->createGame('Bogatell 31.12.2099 18:00');
        $this->joinGameWithOptIn($gameId, 200, NotificationType::BumpedFromGame);
        $queue = new FileQueue('notification_' . $gameId, $this->queuesDir);
        $queue->enqueue(new QueueMessage(['type' => 'garbage']));
        $queue->enqueue(new QueueMessage(new NotificationQueuePayload(NotificationType::BumpedFromGame, $gameId, 200)->jsonSerialize()));

        $this->expectOutputRegex(self::NO_FAILED_MESSAGE);
        $this->createWorker(maxTicks: 2)->run();

        $this->assertSame([200], $this->getSentChatIds());
    }

    public function testAnEmptyQueuesDirectorySendsNothing(): void
    {
        $this->expectOutputString('');
        $this->createWorker()->run();

        $this->assertSame([], $this->getSentChatIds());
    }

    private function joinGameWithOptIn(int $gameId, int $userId, NotificationType $type): void
    {
        $this->createGameUser($gameId, $userId);
        $this->db->update(
            'users',
            ['notifications' => new NotificationSettings()->enable($type)->toInt()],
            ['telegram_user_id' => $userId],
        );
    }

    /** @return list<int> */
    private function getSentChatIds(): array
    {
        $sendMessageCalls = array_filter($this->bot->calls, static fn(array $call): bool => 'sendMessage' === $call['method']);

        return array_values(array_map(static fn(array $call): int => $call['args'][0], $sendMessageCalls));
    }

    /** One tick processes one message. */
    private function createWorker(int $maxTicks = 1): NotificationQueueWorker
    {
        return new NotificationQueueWorker(
            queuesDir: $this->queuesDir,
            maxTicks: $maxTicks,
            processor: new NotificationQueueProcessor(new NotificationSender($this->telegramSender)),
        );
    }
}
