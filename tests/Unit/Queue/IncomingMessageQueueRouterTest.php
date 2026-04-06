<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Queue;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Routing\IncomingMessageQueueRouter;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use Medoo\Medoo;
use PDO;
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
        $this->seedGame(inlineQueryId: 'query_123', inlineMessageId: 'inline_msg_resolved');

        $this->router->route($this->replyToViaBotPayload(inlineQueryId: 'query_123'));

        $this->assertEnqueuedOnce('game_inline_msg_resolved');
    }

    public function testReplyToViaBotMessageWithoutMetaButtonIsSkipped(): void
    {
        $payload = [
            'message' => [
                'message_id' => 54,
                'text' => '12:00',
                'chat' => ['id' => -5127803306, 'type' => 'group'],
                'reply_to_message' => [
                    'message_id' => 53,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot'],
                ],
            ],
        ];

        $this->router->route($payload);

        $this->assertNothingEnqueued();
    }

    public function testReplyToViaBotMessageWithUnknownGameIsSkipped(): void
    {
        $this->seedGame(inlineQueryId: 'other_query', inlineMessageId: 'other_msg');

        $this->router->route($this->replyToViaBotPayload(inlineQueryId: 'unknown_query'));

        $this->assertNothingEnqueued();
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

    private function replyToViaBotPayload(string $inlineQueryId): array
    {
        return [
            'update_id' => 100,
            'message' => [
                'message_id' => 54,
                'text' => '12:00',
                'chat' => ['id' => -5127803306, 'type' => 'group'],
                'reply_to_message' => [
                    'message_id' => 53,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot'],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Leave', 'callback_data' => json_encode(['a' => 'rp', 'q' => $inlineQueryId])],
                                ['text' => 'Join', 'callback_data' => json_encode(['a' => 'ap'])],
                            ],
                        ],
                    ],
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

    private function seedGame(string $inlineQueryId, string $inlineMessageId): void
    {
        $db = new Medoo([
            'type' => 'sqlite',
            'database' => ':memory:',
            'error' => PDO::ERRMODE_EXCEPTION,
        ]);

        $schema = file_get_contents(__DIR__ . '/../../../db/migrations/001_create_games_and_participants.sql');
        $db->pdo->exec($schema);

        $db->insert('games', [
            'title' => 'Test',
            'created_by' => 1,
            'inline_message_id' => $inlineMessageId,
            'inline_query_id' => $inlineQueryId,
        ]);

        Connection::set($db);
    }
}