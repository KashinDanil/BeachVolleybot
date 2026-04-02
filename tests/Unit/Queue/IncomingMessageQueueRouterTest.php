<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Queue;

use BeachVolleybot\Queue\IncomingMessageQueueRouter;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use PHPUnit\Framework\TestCase;

final class IncomingMessageQueueRouterTest extends TestCase
{
    private const string BASE_DIR = '/tmp/test_queues';

    private IncomingMessageQueueRouter $router;

    protected function setUp(): void
    {
        @mkdir(BASE_LOG_DIR, 0777, true);

        SpyQueue::reset();
        $this->router = new IncomingMessageQueueRouter(SpyQueue::class, self::BASE_DIR);
    }

    public function testNewGameCommandRoutesToNewGameQueue(): void
    {
        $this->router->route($this->messagePayload('/new_game Friday 18:00'));

        $this->assertEnqueuedOnce('new_game');
    }

    public function testNewGameCommandWithoutTextRoutesToNewGameQueue(): void
    {
        $this->router->route($this->messagePayload('/new_game'));

        $this->assertEnqueuedOnce('new_game');
    }

    public function testCallbackQueryWithEgCommandRoutesToEditGameQueue(): void
    {
        $this->router->route($this->callbackQueryPayload('/eg_join', 42));

        $this->assertEnqueuedOnce('edit_game_42');
    }

    public function testCallbackQueryUsesMessageIdInQueueName(): void
    {
        $this->router->route($this->callbackQueryPayload('/eg_leave', 99));

        $this->assertEnqueuedOnce('edit_game_99');
    }

    public function testUnrecognizedMessageCommandIsSkipped(): void
    {
        $this->router->route($this->messagePayload('/unknown_command'));

        $this->assertNothingEnqueued();
    }

    public function testNonCommandMessageIsSkipped(): void
    {
        $this->router->route($this->messagePayload('just a regular text'));

        $this->assertNothingEnqueued();
    }

    public function testMessageWithoutTextIsSkipped(): void
    {
        $payload = ['message' => ['message_id' => 1, 'chat' => ['id' => 123]]];

        $this->router->route($payload);

        $this->assertNothingEnqueued();
    }

    public function testCallbackQueryWithUnrecognizedCommandIsSkipped(): void
    {
        $this->router->route($this->callbackQueryPayload('/unknown', 42));

        $this->assertNothingEnqueued();
    }

    public function testCallbackQueryWithoutMessageIdIsSkipped(): void
    {
        $payload = ['callback_query' => ['data' => '/eg_join']];

        $this->router->route($payload);

        $this->assertNothingEnqueued();
    }

    public function testCallbackQueryWithoutDataIsSkipped(): void
    {
        $payload = ['callback_query' => ['message' => ['message_id' => 42]]];

        $this->router->route($payload);

        $this->assertNothingEnqueued();
    }

    public function testUnsupportedPayloadFormatIsSkipped(): void
    {
        $this->router->route(['update_id' => 123]);

        $this->assertNothingEnqueued();
    }

    public function testEnqueuedPayloadMatchesInput(): void
    {
        $payload = $this->messagePayload('/new_game Friday');

        $this->router->route($payload);

        $this->assertSame($payload, SpyQueue::$instances[0]->lastPayload);
    }

    public function testQueueReceivesCorrectBaseDir(): void
    {
        $this->router->route($this->messagePayload('/new_game'));

        $this->assertSame(self::BASE_DIR, SpyQueue::$instances[0]->baseDir);
    }

    private function messagePayload(string $text, int $messageId = 1): array
    {
        return [
            'update_id' => 100,
            'message' => [
                'message_id' => $messageId,
                'text' => $text,
                'chat' => ['id' => 123],
            ],
        ];
    }

    private function callbackQueryPayload(string $data, int $messageId): array
    {
        return [
            'update_id' => 100,
            'callback_query' => [
                'data' => $data,
                'message' => ['message_id' => $messageId],
            ],
        ];
    }

    private function assertEnqueuedOnce(string $expectedQueueName): void
    {
        $this->assertCount(1, SpyQueue::$instances);
        $this->assertSame($expectedQueueName, SpyQueue::$instances[0]->queueName);
        $this->assertSame(1, SpyQueue::$instances[0]->enqueueCount);
    }

    private function assertNothingEnqueued(): void
    {
        $this->assertSame([], SpyQueue::$instances);
    }
}