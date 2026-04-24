<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Tests\Integration\Processors\Stub\BotApiStub;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;

abstract class ProcessorTestCase extends DatabaseTestCase
{
    protected BotApiStub $bot;
    protected TelegramMessageSender $telegramSender;

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        @mkdir(BASE_LOG_DIR, 0777, true);

        $this->bot = new BotApiStub();
        $this->telegramSender = new TelegramMessageSender($this->bot);

        // Each test gets a fresh :memory: DB with gameId starting at 1, but the
        // on-disk weather queue directory persists across tests — drain it so
        // enqueues from prior tests don't leak into assertions.
        foreach (glob(WeatherEnqueuer::QUEUE_DIR . '/*') ?: [] as $path) {
            @unlink($path);
        }
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    protected function seedFullGame(
        string $inlineMessageId = 'msg_1',
        string $inlineQueryId = 'query_1',
        string $title = 'Friday Game 18:00',
    ): int {
        $gameId = $this->createGame(
            title: $title,
            inlineMessageId: $inlineMessageId,
            inlineQueryId: $inlineQueryId,
        );

        return $gameId;
    }

    protected function seedGameWithPlayer(
        int $telegramUserId = 200,
        string $firstName = 'Danil',
        int $volleyball = 0,
        int $net = 0,
        int $position = 1,
        string $inlineMessageId = 'msg_1',
    ): int {
        $gameId = $this->seedFullGame(inlineMessageId: $inlineMessageId);
        $this->createPlayer($telegramUserId, $firstName);
        $this->db->insert('game_players', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'volleyball' => $volleyball,
            'net' => $net,
        ]);
        $this->createSlot($gameId, $telegramUserId, $position);

        return $gameId;
    }

    protected function createSlot(int $gameId, int $telegramUserId, int $position): void
    {
        $this->db->insert('game_slots', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'position' => $position,
        ]);
    }

    protected function callbackQueryPayload(
        string $inlineMessageId,
        string $data,
        int $fromId = 200,
        string $firstName = 'Danil',
    ): array {
        return [
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cbq_1',
                'from' => ['id' => $fromId, 'first_name' => $firstName, 'is_bot' => false],
                'chat_instance' => '-123',
                'inline_message_id' => $inlineMessageId,
                'data' => $data,
            ],
        ];
    }

    protected function chosenInlineResultPayload(
        string $inlineMessageId,
        string $resultId,
        string $query,
        int $fromId = 200,
        string $firstName = 'Danil',
    ): array {
        return [
            'update_id' => 1,
            'chosen_inline_result' => [
                'result_id' => $resultId,
                'from' => ['id' => $fromId, 'first_name' => $firstName, 'is_bot' => false],
                'query' => $query,
                'inline_message_id' => $inlineMessageId,
            ],
        ];
    }

    protected function inlineQueryPayload(
        string $inlineQueryId,
        string $query,
        int $fromId = 200,
        string $firstName = 'Danil',
    ): array {
        return [
            'update_id' => 1,
            'inline_query' => [
                'id' => $inlineQueryId,
                'from' => ['id' => $fromId, 'first_name' => $firstName, 'is_bot' => false],
                'query' => $query,
                'offset' => '',
            ],
        ];
    }

    protected function locationMessagePayload(
        float $latitude,
        float $longitude,
        string $inlineQueryId,
        int $fromId = 200,
        string $firstName = 'Danil',
        int $chatId = -5127803306,
    ): array {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 54,
                'from' => ['id' => $fromId, 'first_name' => $firstName, 'is_bot' => false],
                'chat' => ['id' => $chatId, 'type' => 'group'],
                'date' => 1700000000,
                'location' => ['latitude' => $latitude, 'longitude' => $longitude],
                'reply_to_message' => [
                    'message_id' => 53,
                    'from' => ['id' => $fromId, 'first_name' => $firstName, 'is_bot' => false],
                    'chat' => ['id' => $chatId, 'type' => 'group'],
                    'date' => 1699999000,
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
        ];
    }

    protected function replyMessagePayload(
        string $text,
        string $inlineQueryId,
        int $fromId = 200,
        string $firstName = 'Danil',
        int $chatId = -5127803306,
    ): array {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 54,
                'from' => ['id' => $fromId, 'first_name' => $firstName, 'is_bot' => false],
                'chat' => ['id' => $chatId, 'type' => 'group'],
                'date' => 1700000000,
                'text' => $text,
                'reply_to_message' => [
                    'message_id' => 53,
                    'from' => ['id' => $fromId, 'first_name' => $firstName, 'is_bot' => false],
                    'chat' => ['id' => $chatId, 'type' => 'group'],
                    'date' => 1699999000,
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
        ];
    }

    protected function viaBotKeyboardMessagePayload(int $chatId = -5127803306, int $fromId = 200): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 60,
                'from' => ['id' => $fromId, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => $chatId, 'type' => 'group'],
                'date' => 1700000000,
                'text' => 'Game body',
                'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Join', 'callback_data' => '{"a":"j"}'],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function editedLocationMessagePayload(
        float $latitude,
        float $longitude,
        string $inlineQueryId,
        int $fromId = 200,
        string $firstName = 'Danil',
        int $chatId = -5127803306,
    ): array {
        return [
            'update_id' => 1,
            'edited_message' => [
                'message_id' => 180,
                'from' => ['id' => $fromId, 'first_name' => $firstName, 'is_bot' => false],
                'chat' => ['id' => $chatId, 'type' => 'supergroup'],
                'date' => 1700000000,
                'reply_to_message' => [
                    'message_id' => 171,
                    'from' => ['id' => $fromId, 'first_name' => $firstName, 'is_bot' => false],
                    'chat' => ['id' => $chatId, 'type' => 'supergroup'],
                    'date' => 1699999000,
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
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'live_period' => 900,
                ],
            ],
        ];
    }

    protected function pinNotificationPayload(int $chatId, int $messageId, int $pinnedMessageId): array
    {
        $pinned = [
            'message_id' => $pinnedMessageId,
            'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
            'chat' => ['id' => $chatId, 'type' => 'supergroup'],
            'date' => 1700000000,
            'text' => 'game body',
            'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
        ];

        return [
            'update_id' => 1,
            'message' => [
                'message_id' => $messageId,
                'from' => ['id' => 999, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                'chat' => ['id' => $chatId, 'type' => 'supergroup'],
                'date' => 1700000001,
                'reply_to_message' => $pinned,
                'pinned_message' => $pinned,
            ],
        ];
    }

    protected function privateMessagePayload(
        string $text,
        int $fromId = 12345678,
        string $firstName = 'Danil',
    ): array {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 109,
                'from' => ['id' => $fromId, 'first_name' => $firstName, 'is_bot' => false],
                'chat' => ['id' => $fromId, 'first_name' => $firstName, 'type' => 'private'],
                'date' => 1700000000,
                'text' => $text,
            ],
        ];
    }

    protected function adminCallbackQueryPayload(
        string $data,
        int $fromId = 12345678,
        string $firstName = 'Danil',
        int $chatId = 12345678,
        int $messageId = 109,
    ): array {
        return [
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cbq_admin_1',
                'from' => ['id' => $fromId, 'first_name' => $firstName, 'is_bot' => false],
                'chat_instance' => '-123',
                'message' => [
                    'message_id' => $messageId,
                    'from' => ['id' => 999, 'first_name' => 'Bot', 'is_bot' => true],
                    'chat' => ['id' => $chatId, 'first_name' => $firstName, 'type' => 'private'],
                    'date' => 1700000000,
                    'text' => 'Settings',
                ],
                'data' => $data,
            ],
        ];
    }

    protected function assertMessageSent(): void
    {
        $calls = array_filter($this->bot->calls, fn($c) => 'sendMessage' === $c['method']);
        $this->assertNotEmpty($calls, 'Expected sendMessage to be called');
    }

    protected function assertMessageNotSent(): void
    {
        $calls = array_filter($this->bot->calls, fn($c) => 'sendMessage' === $c['method']);
        $this->assertEmpty($calls, 'Expected sendMessage NOT to be called');
    }

    protected function assertDocumentSent(): void
    {
        $calls = array_filter($this->bot->calls, fn($c) => 'sendDocument' === $c['method']);
        $this->assertNotEmpty($calls, 'Expected sendDocument to be called');
    }

    protected function assertAnsweredWith(string $expectedText): void
    {
        $answerCalls = array_filter($this->bot->calls, fn($c) => 'answerCallbackQuery' === $c['method']);
        $this->assertNotEmpty($answerCalls, 'Expected answerCallbackQuery to be called');
        $lastCall = end($answerCalls);
        $this->assertSame($expectedText, $lastCall['args'][1] ?? null);
    }

    protected function assertMessageEdited(): void
    {
        $editCalls = array_filter($this->bot->calls, fn($c) => 'editMessageText' === $c['method']);
        $this->assertNotEmpty($editCalls, 'Expected editMessageText to be called');
    }

    protected function assertMessageNotEdited(): void
    {
        $editCalls = array_filter($this->bot->calls, fn($c) => 'editMessageText' === $c['method']);
        $this->assertEmpty($editCalls, 'Expected editMessageText NOT to be called');
    }

    protected function assertKeyboardRemoved(): void
    {
        $calls = array_filter($this->bot->calls, fn($c) => 'editMessageReplyMarkup' === $c['method']);
        $this->assertNotEmpty($calls, 'Expected editMessageReplyMarkup to be called');
    }

    protected function assertKeyboardNotRemoved(): void
    {
        $calls = array_filter($this->bot->calls, fn($c) => 'editMessageReplyMarkup' === $c['method']);
        $this->assertEmpty($calls, 'Expected editMessageReplyMarkup NOT to be called');
    }

    protected function assertReactedWithConfused(): void
    {
        $this->assertReactedWith('👎');
    }

    protected function assertReactedWith(string $emoji): void
    {
        $reactionCalls = array_filter(
            $this->bot->calls,
            function ($c) use ($emoji) {
                if ('call' !== $c['method'] || 'setMessageReaction' !== ($c['args'][0] ?? null)) {
                    return false;
                }

                $reaction = json_decode($c['args'][1]['reaction'] ?? '[]', true);

                return ($reaction[0]['emoji'] ?? null) === $emoji;
            },
        );
        $this->assertNotEmpty($reactionCalls, "Expected reaction with $emoji to be set");
    }
}
