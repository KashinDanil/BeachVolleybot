<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Queue;

use BeachVolleybot\Routing\IncomingMessageQueueRouter;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use PHPUnit\Framework\TestCase;

final class IncomingMessageQueueRouterDmTest extends TestCase
{
    private const string BASE_DIR = '/tmp/test_queues';

    private IncomingMessageQueueRouter $router;

    public function testPrivateMessageRoutesToDmQueue(): void
    {
        $this->router->route($this->privateMessageUpdate(12345678, '/settings'));

        $this->assertEnqueuedOnce('dm_12345678');
    }

    public function testPrivateMessageWithAnyTextRoutesToDmQueue(): void
    {
        $this->router->route($this->privateMessageUpdate(12345, 'hello'));

        $this->assertEnqueuedOnce('dm_12345');
    }

    public function testCallbackQueryWithoutInlineMessageIdRoutesToDmQueue(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'callback_query' => [
                'id' => 'cbq_1',
                'from' => ['id' => 12345678, 'first_name' => 'Danil', 'is_bot' => false],
                'chat_instance' => '-123',
                'message' => [
                    'message_id' => 109,
                    'from' => ['id' => 999, 'first_name' => 'Bot', 'is_bot' => true],
                    'chat' => ['id' => 12345678, 'type' => 'private'],
                    'date' => 1700000000,
                    'text' => 'Settings',
                ],
                'data' => '{"aa":"logs"}',
            ],
        ]);

        $this->router->route($update);

        $this->assertEnqueuedOnce('dm_12345678');
    }

    public function testCallbackQueryWithInlineMessageIdStillGoesToGameQueue(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'callback_query' => [
                'id' => 'cbq_1',
                'from' => ['id' => 12345678, 'first_name' => 'Danil', 'is_bot' => false],
                'chat_instance' => '-123',
                'inline_message_id' => 'inline_msg_abc',
                'data' => '{"a":"j"}',
            ],
        ]);

        $this->router->route($update);

        $this->assertEnqueuedOnce('game_inline_msg_abc');
    }

    protected function setUp(): void
    {
        SpyQueue::reset();
        $this->router = new IncomingMessageQueueRouter(SpyQueue::class, self::BASE_DIR);
    }

    private function privateMessageUpdate(int $userId, string $text): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 100,
            'message' => [
                'message_id' => 109,
                'from' => ['id' => $userId, 'first_name' => 'Test', 'is_bot' => false],
                'chat' => ['id' => $userId, 'type' => 'private'],
                'date' => 1700000000,
                'text' => $text,
            ],
        ]);
    }

    private function assertEnqueuedOnce(string $expectedQueueName): void
    {
        $this->assertCount(1, SpyQueue::$instances);
        $this->assertSame($expectedQueueName, SpyQueue::$instances[0]->queueName);
        $this->assertSame(1, SpyQueue::$instances[0]->enqueueCount);
    }
}
