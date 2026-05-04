<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Queue;

use BeachVolleybot\Routing\IncomingMessageQueueRouter;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
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

    // --- chosen_inline_result ---

    public function testChosenInlineResultRoutesToGameQueueByResultId(): void
    {
        $this->router->route($this->chosenInlineResultUpdate(resultId: 'query_abc'));

        $this->assertEnqueuedOnce('game_query_abc');
    }

    public function testPathTraversalInChosenInlineResultIsSanitized(): void
    {
        $this->router->route($this->chosenInlineResultUpdate(resultId: '../../etc/evil'));

        $this->assertEnqueuedOnce('game_______etc_evil');
    }

    public function testSpecialCharactersInResultIdAreSanitized(): void
    {
        $this->router->route($this->chosenInlineResultUpdate(resultId: 'AgAAA+Fsq/AP=='));

        $this->assertEnqueuedOnce('game_AgAAA_Fsq_AP__');
    }

    // --- callback_query ---

    public function testInlineCallbackQueryRoutesToGameQueueByInlineQueryIdFromCallbackData(): void
    {
        $this->router->route($this->inlineCallbackQueryUpdate(inlineQueryId: 'query_abc'));

        $this->assertEnqueuedOnce('game_query_abc');
    }

    public function testTwoCallbacksFromDifferentInlineMessagesOfTheSameGameShareTheQueueName(): void
    {
        $this->router->route($this->inlineCallbackQueryUpdate(inlineQueryId: 'query_x', inlineMessageId: 'msg_a'));
        $this->router->route($this->inlineCallbackQueryUpdate(inlineQueryId: 'query_x', inlineMessageId: 'msg_b'));

        $this->assertCount(2, SpyQueue::$instances);
        $this->assertSame('game_query_x', SpyQueue::$instances[0]->queueName);
        $this->assertSame('game_query_x', SpyQueue::$instances[1]->queueName);
    }

    public function testInlineCallbackQueryWithoutInlineQueryIdInDataIsSkipped(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'callback_query' => [
                'id' => 'cbq_1',
                'data' => '{"a":"j"}',
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'chat_instance' => '-123',
                'inline_message_id' => 'msg_x',
            ],
        ]);

        $this->router->route($update);

        $this->assertNothingEnqueued();
    }

    public function testCallbackQueryWithoutInlineMessageIdRoutesToDmQueue(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'callback_query' => [
                'id' => 'cbq_1',
                'data' => '{"a":"j"}',
                'from' => ['id' => 456, 'first_name' => 'Test', 'is_bot' => false],
                'chat_instance' => '-123',
            ],
        ]);

        $this->router->route($update);

        $this->assertEnqueuedOnce('dm_456');
    }

    // --- reply messages ---

    public function testReplyToViaBotMessageRoutesToGameQueueByInlineQueryIdFromMetaButton(): void
    {
        $this->router->route($this->replyToViaBotUpdate(inlineQueryId: 'query_123'));

        $this->assertEnqueuedOnce('game_query_123');
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
        $this->router->route($this->editedMessageUpdate(inlineQueryId: 'query_456'));

        $this->assertEnqueuedOnce('game_query_456');
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

    public function testEditedMessageInPrivateChatRoutesToDmQueue(): void
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

        $this->assertEnqueuedOnce('dm_123');
    }

    // --- payload preservation / wiring ---

    public function testEnqueuedPayloadMatchesInput(): void
    {
        $payload = $this->chosenInlineResultPayload(resultId: 'query_abc');
        $update = TelegramUpdate::fromArray($payload);

        $this->router->route($update);

        $this->assertSame($payload, SpyQueue::$instances[0]->lastPayload);
    }

    public function testQueueReceivesCorrectBaseDir(): void
    {
        $this->router->route($this->chosenInlineResultUpdate(resultId: 'query_abc'));

        $this->assertSame(self::BASE_DIR, SpyQueue::$instances[0]->baseDir);
    }

    // --- Helpers ---

    private function chosenInlineResultPayload(string $resultId, string $inlineMessageId = 'msg_x'): array
    {
        return [
            'update_id' => 100,
            'chosen_inline_result' => [
                'result_id' => $resultId,
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'query' => 'Test game',
                'inline_message_id' => $inlineMessageId,
            ],
        ];
    }

    private function chosenInlineResultUpdate(string $resultId): TelegramUpdate
    {
        return TelegramUpdate::fromArray($this->chosenInlineResultPayload($resultId));
    }

    private function inlineCallbackQueryUpdate(string $inlineQueryId, string $inlineMessageId = 'msg_x'): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 100,
            'callback_query' => [
                'id' => 'cbq_1',
                'data' => json_encode(['a' => 'j', 'q' => $inlineQueryId]),
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
}
