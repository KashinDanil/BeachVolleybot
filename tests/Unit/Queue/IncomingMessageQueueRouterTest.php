<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Queue;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Routing\IncomingMessageQueueRouter;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
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
        $this->router->route($this->chosenInlineResultUpdate('inline_msg_abc'));

        $this->assertEnqueuedOnce('game_inline_msg_abc');
    }

    public function testChosenInlineResultWithoutInlineMessageIdIsSkipped(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'chosen_inline_result' => [
                'result_id' => 'new_game_123',
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'query' => 'Test game',
            ],
        ]);

        $this->router->route($update);

        $this->assertNothingEnqueued();
    }

    public function testCallbackQueryRoutesToGameQueueByInlineMessageId(): void
    {
        $this->router->route($this->callbackQueryUpdate('/eg_+p', 'inline_msg_abc'));

        $this->assertEnqueuedOnce('game_inline_msg_abc');
    }

    public function testCallbackQueryWithoutInlineMessageIdRoutesToDmQueue(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'callback_query' => [
                'id' => 'cbq_1',
                'data' => '/eg_+p',
                'from' => ['id' => 456, 'first_name' => 'Test', 'is_bot' => false],
                'chat_instance' => '-123',
            ],
        ]);

        $this->router->route($update);

        $this->assertEnqueuedOnce('dm_456');
    }

    public function testReplyToViaBotMessageRoutesToGameQueue(): void
    {
        $this->seedGame(inlineQueryId: 'query_123', inlineMessageId: 'inline_msg_resolved');

        $this->router->route($this->replyToViaBotUpdate(inlineQueryId: 'query_123'));

        $this->assertEnqueuedOnce('game_inline_msg_resolved');
    }

    public function testReplyToViaBotMessageWithoutMetaButtonIsSkipped(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'message' => [
                'message_id' => 54,
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'text' => '12:00',
                'chat' => ['id' => -5127803306, 'type' => 'group'],
                'date' => 1700000000,
                'reply_to_message' => [
                    'message_id' => 53,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true],
                    'chat' => ['id' => -5127803306, 'type' => 'group'],
                    'date' => 1700000000,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                ],
            ],
        ]);

        $this->router->route($update);

        $this->assertNothingEnqueued();
    }

    public function testReplyToViaBotMessageWithUnknownGameIsSkipped(): void
    {
        $this->seedGame(inlineQueryId: 'other_query', inlineMessageId: 'other_msg');

        $this->router->route($this->replyToViaBotUpdate(inlineQueryId: 'unknown_query'));

        $this->assertNothingEnqueued();
    }

    public function testPrivateMessageRoutesToDmQueue(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'message' => [
                'message_id' => 54,
                'from' => ['id' => 123, 'first_name' => 'Test', 'is_bot' => false],
                'chat' => ['id' => 123, 'type' => 'private'],
                'date' => 1700000000,
                'reply_to_message' => [
                    'message_id' => 53,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true],
                    'chat' => ['id' => 123, 'type' => 'private'],
                    'date' => 1700000000,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                ],
            ],
        ]);

        $this->router->route($update);

        $this->assertEnqueuedOnce('dm_123');
    }

    public function testNonReplyGroupMessageIsSkipped(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'message' => [
                'message_id' => 54,
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'text' => 'just a message',
                'chat' => ['id' => 123, 'type' => 'group'],
                'date' => 1700000000,
            ],
        ]);

        $this->router->route($update);

        $this->assertNothingEnqueued();
    }

    public function testReplyToNonViaBotMessageIsSkipped(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'message' => [
                'message_id' => 54,
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'chat' => ['id' => 123, 'type' => 'group'],
                'date' => 1700000000,
                'reply_to_message' => [
                    'message_id' => 53,
                    'from' => ['id' => 2, 'first_name' => 'User', 'is_bot' => false],
                    'chat' => ['id' => 123, 'type' => 'group'],
                    'date' => 1700000000,
                ],
            ],
        ]);

        $this->router->route($update);

        $this->assertNothingEnqueued();
    }

    public function testEditedMessageIsSkipped(): void
    {
        $this->router->route($this->editedMessageUpdate(inlineQueryId: 'query_456'));

        $this->assertNothingEnqueued();
    }

    public function testEnqueuedPayloadMatchesInput(): void
    {
        $payload = $this->chosenInlineResultPayload('inline_msg_abc');
        $update = TelegramUpdate::fromArray($payload);

        $this->router->route($update);

        $this->assertSame($payload, SpyQueue::$instances[0]->lastPayload);
    }

    public function testPathTraversalInChosenInlineResultIsSanitized(): void
    {
        $this->router->route($this->chosenInlineResultUpdate('../../etc/evil'));

        $this->assertEnqueuedOnce('game_______etc_evil');
    }

    public function testPathTraversalInCallbackQueryIsSanitized(): void
    {
        $this->router->route($this->callbackQueryUpdate('/eg_+p', '../../etc/evil'));

        $this->assertEnqueuedOnce('game_______etc_evil');
    }

    public function testSpecialCharactersInInlineMessageIdAreSanitized(): void
    {
        $this->router->route($this->chosenInlineResultUpdate('AgAAA+Fsq/AP=='));

        $this->assertEnqueuedOnce('game_AgAAA_Fsq_AP__');
    }

    public function testQueueReceivesCorrectBaseDir(): void
    {
        $this->router->route($this->chosenInlineResultUpdate('inline_msg_abc'));

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

    private function chosenInlineResultUpdate(string $inlineMessageId): TelegramUpdate
    {
        return TelegramUpdate::fromArray($this->chosenInlineResultPayload($inlineMessageId));
    }

    private function callbackQueryUpdate(string $data, string $inlineMessageId): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 100,
            'callback_query' => [
                'id' => 'cbq_1',
                'data' => $data,
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'chat_instance' => '-123',
                'inline_message_id' => $inlineMessageId,
            ],
        ]);
    }

    private function replyToViaBotUpdate(string $inlineQueryId): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 100,
            'message' => [
                'message_id' => 54,
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'text' => '12:00',
                'chat' => ['id' => -5127803306, 'type' => 'group'],
                'date' => 1700000000,
                'reply_to_message' => [
                    'message_id' => 53,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true],
                    'chat' => ['id' => -5127803306, 'type' => 'group'],
                    'date' => 1700000000,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Leave', 'callback_data' => json_encode(['a' => 'l', 'q' => $inlineQueryId])],
                                ['text' => 'Join', 'callback_data' => json_encode(['a' => 'j'])],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function editedMessageUpdate(string $inlineQueryId): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 100,
            'edited_message' => [
                'message_id' => 147,
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'chat' => ['id' => -1003759398496, 'type' => 'supergroup'],
                'date' => 1700000000,
                'reply_to_message' => [
                    'message_id' => 146,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true],
                    'chat' => ['id' => -1003759398496, 'type' => 'supergroup'],
                    'date' => 1700000000,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Leave', 'callback_data' => json_encode(['a' => 'l', 'q' => $inlineQueryId])],
                                ['text' => 'Join', 'callback_data' => json_encode(['a' => 'j'])],
                            ],
                        ],
                    ],
                ],
                'location' => [
                    'latitude' => 41.413114,
                    'longitude' => 2.194864,
                    'live_period' => 900,
                    'heading' => 171,
                    'horizontal_accuracy' => 6,
                ],
            ],
        ]);
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

        $schema = file_get_contents(__DIR__ . '/../../../migrations/001_create_games_and_participants.sql');
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
