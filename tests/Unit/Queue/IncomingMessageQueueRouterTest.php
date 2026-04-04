<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Queue;

use BeachVolleybot\Routing\IncomingMessageQueueRouter;
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

    public function testChosenInlineResultRoutesToGameQueue(): void
    {
        $this->router->route($this->chosenInlineResultPayload('inline_msg_abc'));

        $this->assertEnqueuedOnce('game_inline_msg_abc');
    }

    public function testChosenInlineResultWithoutInlineMessageIdIsSkipped(): void
    {
        $payload = [
            'chosen_inline_result' => [
                'result_id' => 'new_game_123',
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'query' => 'Test game',
            ],
        ];

        $this->router->route($payload);

        $this->assertNothingEnqueued();
    }

    public function testCallbackQueryRoutesToGameQueueByInlineMessageId(): void
    {
        $this->router->route($this->callbackQueryPayload('/eg_+p', 'inline_msg_abc'));

        $this->assertEnqueuedOnce('game_inline_msg_abc');
    }

    public function testCallbackQueryWithoutInlineMessageIdIsSkipped(): void
    {
        $payload = ['callback_query' => ['data' => '/eg_+p']];

        $this->router->route($payload);

        $this->assertNothingEnqueued();
    }

    public function testReplyToViaBotMessageRoutesToGameQueue(): void
    {
        $this->router->route($this->replyToViaBotPayload(chatId: -5127803306, repliedMessageId: 53));

        $this->assertEnqueuedOnce('game_-5127803306_53');
    }

    public function testNonGroupMessageIsSkipped(): void
    {
        $payload = [
            'message' => [
                'message_id' => 54,
                'chat' => ['id' => 123, 'type' => 'private'],
                'reply_to_message' => [
                    'message_id' => 53,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot'],
                ],
            ],
        ];

        $this->router->route($payload);

        $this->assertNothingEnqueued();
    }

    public function testNonReplyGroupMessageIsSkipped(): void
    {
        $payload = [
            'message' => [
                'message_id' => 54,
                'text' => 'just a message',
                'chat' => ['id' => 123, 'type' => 'group'],
            ],
        ];

        $this->router->route($payload);

        $this->assertNothingEnqueued();
    }

    public function testReplyToNonViaBotMessageIsSkipped(): void
    {
        $payload = [
            'message' => [
                'message_id' => 54,
                'chat' => ['id' => 123, 'type' => 'group'],
                'reply_to_message' => [
                    'message_id' => 53,
                ],
            ],
        ];

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
        $payload = $this->chosenInlineResultPayload('inline_msg_abc');

        $this->router->route($payload);

        $this->assertSame($payload, SpyQueue::$instances[0]->lastPayload);
    }

    public function testQueueReceivesCorrectBaseDir(): void
    {
        $this->router->route($this->chosenInlineResultPayload('inline_msg_abc'));

        $this->assertSame(self::BASE_DIR, SpyQueue::$instances[0]->baseDir);
    }

    private function chosenInlineResultPayload(string $inlineMessageId): array
    {
        return [
            'update_id' => 100,
            'chosen_inline_result' => [
                'result_id' => 'new_game_123',
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'query' => 'Test game',
                'inline_message_id' => $inlineMessageId,
            ],
        ];
    }

    private function callbackQueryPayload(string $data, string $inlineMessageId): array
    {
        return [
            'update_id' => 100,
            'callback_query' => [
                'data' => $data,
                'inline_message_id' => $inlineMessageId,
            ],
        ];
    }

    private function replyToViaBotPayload(int $chatId, int $repliedMessageId): array
    {
        return [
            'update_id' => 100,
            'message' => [
                'message_id' => 54,
                'text' => '12:00',
                'chat' => ['id' => $chatId, 'type' => 'group'],
                'reply_to_message' => [
                    'message_id' => $repliedMessageId,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot'],
                ],
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