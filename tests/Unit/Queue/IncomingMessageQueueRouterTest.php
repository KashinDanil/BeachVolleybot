<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Queue;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Processors\ProcessorRegistryFactory;
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
    private Medoo $db;

    protected function setUp(): void
    {
        @mkdir(BASE_LOG_DIR, 0777, true);

        $this->db = new Medoo([
            'type' => 'sqlite',
            'database' => ':memory:',
            'error' => PDO::ERRMODE_EXCEPTION,
            'command' => ['PRAGMA foreign_keys = ON'],
        ]);
        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../migrations/001_create_games_and_participants.sql'));
        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../migrations/004_split_game_inline_messages.sql'));
        Connection::set($this->db);

        SpyQueue::reset();
        $this->router = new IncomingMessageQueueRouter(SpyQueue::class, self::BASE_DIR, ProcessorRegistryFactory::create());
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testChosenInlineResultIsNotEnqueuedBecauseItIsHandledSynchronously(): void
    {
        $this->router->route($this->chosenInlineResultUpdate(resultId: 'query_abc'));

        $this->assertNothingEnqueued();
    }

    // --- callback_query ---

    public function testInlineCallbackQueryRoutesToGameQueueByGameIdFromJunctionTable(): void
    {
        $gameId = $this->seedGame(inlineQueryId: 'q1', inlineMessageId: 'msg_a');

        $this->router->route($this->inlineCallbackQueryUpdate(inlineMessageId: 'msg_a'));

        $this->assertEnqueuedOnce('game_' . $gameId);
    }

    public function testTwoCallbacksFromDifferentInlineMessagesOfTheSameGameShareTheQueueName(): void
    {
        $gameId = $this->seedGame(inlineQueryId: 'q1', inlineMessageId: 'msg_a');
        $this->attachInlineMessage($gameId, 'msg_b');

        $this->router->route($this->inlineCallbackQueryUpdate(inlineMessageId: 'msg_a'));
        $this->router->route($this->inlineCallbackQueryUpdate(inlineMessageId: 'msg_b'));

        $this->assertCount(2, SpyQueue::$instances);
        $this->assertSame('game_' . $gameId, SpyQueue::$instances[0]->queueName);
        $this->assertSame('game_' . $gameId, SpyQueue::$instances[1]->queueName);
    }

    public function testInlineCallbackQueryForUnknownInlineMessageIdIsSkipped(): void
    {
        $this->router->route($this->inlineCallbackQueryUpdate(inlineMessageId: 'nonexistent'));

        $this->assertNothingEnqueued();
    }

    public function testNonInlineUserCallbackQueryRoutesToDmQueue(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'callback_query' => [
                'id' => 'cbq_1',
                'data' => '{"ua":"ugl"}',
                'from' => ['id' => 456, 'first_name' => 'Test', 'is_bot' => false],
                'chat_instance' => '-123',
            ],
        ]);

        $this->router->route($update);

        $this->assertEnqueuedOnce('dm_456');
    }

    // --- reply messages ---

    public function testReplyToViaBotMessageRoutesToGameQueueByGameIdFromMetaButtonInlineQueryId(): void
    {
        $gameId = $this->seedGame(inlineQueryId: 'query_123', inlineMessageId: 'msg_x');

        $this->router->route($this->replyToViaBotUpdate(inlineQueryId: 'query_123'));

        $this->assertEnqueuedOnce('game_' . $gameId);
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

    public function testReplyToViaBotMessageWithUnknownInlineQueryIdIsSkipped(): void
    {
        $this->router->route($this->replyToViaBotUpdate(inlineQueryId: 'unknown_query'));

        $this->assertNothingEnqueued();
    }

    public function testPrivateReplyToViaBotMessageWithoutMetaButtonIsSkipped(): void
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

        $this->assertNothingEnqueued();
    }

    public function testPrivateReplyToViaBotMessageRoutesToGameQueueByGameId(): void
    {
        $gameId = $this->seedGame(inlineQueryId: 'query_dm', inlineMessageId: 'msg_dm');
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'message' => [
                'message_id' => 54,
                'from' => ['id' => 123, 'first_name' => 'Test', 'is_bot' => false],
                'chat' => ['id' => 123, 'type' => 'private'],
                'date' => 1700000000,
                'text' => '12:00',
                'reply_to_message' => [
                    'message_id' => 53,
                    'from' => ['id' => 123, 'first_name' => 'Test', 'is_bot' => false],
                    'chat' => ['id' => 123, 'type' => 'private'],
                    'date' => 1700000000,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Leave', 'callback_data' => json_encode(['a' => 'l', 'q' => 'query_dm'])],
                                ['text' => 'Join', 'callback_data' => json_encode(['a' => 'j'])],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->router->route($update);

        $this->assertEnqueuedOnce('game_' . $gameId);
    }

    public function testEditedPrivateMessageReplyToViaBotRoutesToGameQueue(): void
    {
        $gameId = $this->seedGame(inlineQueryId: 'query_dm_edit', inlineMessageId: 'msg_dm_edit');
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'edited_message' => [
                'message_id' => 147,
                'from' => ['id' => 123, 'first_name' => 'Test', 'is_bot' => false],
                'chat' => ['id' => 123, 'type' => 'private'],
                'date' => 1700000000,
                'reply_to_message' => [
                    'message_id' => 146,
                    'from' => ['id' => 123, 'first_name' => 'Test', 'is_bot' => false],
                    'chat' => ['id' => 123, 'type' => 'private'],
                    'date' => 1700000000,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Leave', 'callback_data' => json_encode(['a' => 'l', 'q' => 'query_dm_edit'])],
                                ['text' => 'Join', 'callback_data' => json_encode(['a' => 'j'])],
                            ],
                        ],
                    ],
                ],
                'location' => [
                    'latitude' => 41.413114,
                    'longitude' => 2.194864,
                    'live_period' => 900,
                ],
            ],
        ]);

        $this->router->route($update);

        $this->assertEnqueuedOnce('game_' . $gameId);
    }

    public function testPinServiceMessageFromThisBotRoutesToPinQueue(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'message' => [
                'message_id' => 353,
                'from' => ['id' => 999, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                'chat' => ['id' => -1003759398496, 'type' => 'supergroup'],
                'date' => 1700000000,
                'pinned_message' => [
                    'message_id' => 352,
                    'from' => ['id' => 1, 'first_name' => 'User', 'is_bot' => false],
                    'chat' => ['id' => -1003759398496, 'type' => 'supergroup'],
                    'date' => 1700000000,
                    'text' => 'pinned text',
                    'via_bot' => ['id' => 999, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                ],
            ],
        ]);

        $this->router->route($update);

        $this->assertEnqueuedOnce('pin_-1003759398496');
    }

    public function testPinServiceMessageFromOtherBotIsSkipped(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'message' => [
                'message_id' => 353,
                'from' => ['id' => 777, 'first_name' => 'Other', 'is_bot' => true, 'username' => 'some_other_bot'],
                'chat' => ['id' => -1003759398496, 'type' => 'supergroup'],
                'date' => 1700000000,
                'pinned_message' => [
                    'message_id' => 352,
                    'from' => ['id' => 1, 'first_name' => 'User', 'is_bot' => false],
                    'chat' => ['id' => -1003759398496, 'type' => 'supergroup'],
                    'date' => 1700000000,
                    'text' => 'pinned text',
                ],
            ],
        ]);

        $this->router->route($update);

        $this->assertNothingEnqueued();
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

    // --- edited messages ---

    public function testEditedMessageWithLocationRoutesToGameQueue(): void
    {
        $gameId = $this->seedGame(inlineQueryId: 'query_456', inlineMessageId: 'msg_loc');

        $this->router->route($this->editedMessageUpdate(inlineQueryId: 'query_456'));

        $this->assertEnqueuedOnce('game_' . $gameId);
    }

    public function testEditedMessageWithoutLocationIsSkipped(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'edited_message' => [
                'message_id' => 147,
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'chat' => ['id' => -1003759398496, 'type' => 'supergroup'],
                'date' => 1700000000,
                'text' => 'edited text',
                'reply_to_message' => [
                    'message_id' => 146,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true],
                    'chat' => ['id' => -1003759398496, 'type' => 'supergroup'],
                    'date' => 1700000000,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                ],
            ],
        ]);

        $this->router->route($update);

        $this->assertNothingEnqueued();
    }

    public function testEditedPrivateMessageWithoutReplyToBotIsSkipped(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'edited_message' => [
                'message_id' => 147,
                'from' => ['id' => 123, 'first_name' => 'Test', 'is_bot' => false],
                'chat' => ['id' => 123, 'type' => 'private'],
                'date' => 1700000000,
                'location' => [
                    'latitude' => 41.413114,
                    'longitude' => 2.194864,
                    'live_period' => 900,
                ],
            ],
        ]);

        $this->router->route($update);

        $this->assertNothingEnqueued();
    }

    // --- payload preservation / wiring ---

    public function testEnqueuedPayloadMatchesInput(): void
    {
        $this->seedGame(inlineQueryId: 'q1', inlineMessageId: 'msg_a');
        $payload = $this->inlineCallbackQueryPayload(inlineMessageId: 'msg_a');
        $update = TelegramUpdate::fromArray($payload);

        $this->router->route($update);

        $this->assertSame($payload, SpyQueue::$instances[0]->lastPayload);
    }

    public function testQueueReceivesCorrectBaseDir(): void
    {
        $this->seedGame(inlineQueryId: 'q1', inlineMessageId: 'msg_a');
        $this->router->route($this->inlineCallbackQueryUpdate(inlineMessageId: 'msg_a'));

        $this->assertSame(self::BASE_DIR, SpyQueue::$instances[0]->baseDir);
    }

    // --- Helpers ---

    private function seedGame(string $inlineQueryId, string $inlineMessageId): int
    {
        $this->db->insert('games', [
            'title' => 'Test',
            'created_by' => 1,
            'inline_query_id' => $inlineQueryId,
        ]);
        $gameId = (int) $this->db->id();
        $this->attachInlineMessage($gameId, $inlineMessageId);

        return $gameId;
    }

    private function attachInlineMessage(int $gameId, string $inlineMessageId): void
    {
        $this->db->insert('game_inline_messages', [
            'game_id' => $gameId,
            'inline_message_id' => $inlineMessageId,
        ]);
    }

    private function chosenInlineResultUpdate(string $resultId): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 100,
            'chosen_inline_result' => [
                'result_id' => $resultId,
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'query' => 'Test game',
                'inline_message_id' => 'msg_x',
            ],
        ]);
    }

    private function inlineCallbackQueryPayload(string $inlineMessageId): array
    {
        return [
            'update_id' => 100,
            'callback_query' => [
                'id' => 'cbq_1',
                'data' => '{"a":"j"}',
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'chat_instance' => '-123',
                'inline_message_id' => $inlineMessageId,
            ],
        ];
    }

    private function inlineCallbackQueryUpdate(string $inlineMessageId): TelegramUpdate
    {
        return TelegramUpdate::fromArray($this->inlineCallbackQueryPayload($inlineMessageId));
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
}
