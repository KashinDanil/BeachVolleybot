<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Tests\Integration\Processors\Stub\BotApiStub;

abstract class ProcessorTestCase extends DatabaseTestCase
{
    protected BotApiStub $bot;

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        @mkdir(BASE_LOG_DIR, 0777, true);

        $this->bot = new BotApiStub();
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
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot'],
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
}
