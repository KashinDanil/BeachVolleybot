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

final class IncomingMessageQueueRouterDmTest extends TestCase
{
    private const string BASE_DIR = '/tmp/test_queues';

    private IncomingMessageQueueRouter $router;
    private Medoo $db;

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

    public function testPrivateReplyToViaBotGameMessageRoutesToGameQueueByGameId(): void
    {
        $this->db->insert('games', ['title' => 'Test', 'created_by' => 1, 'inline_query_id' => 'query_dm']);
        $gameId = (int) $this->db->id();
        $this->db->insert('game_inline_messages', ['game_id' => $gameId, 'inline_message_id' => 'msg_dm']);

        $update = TelegramUpdate::fromArray([
            'update_id' => 100,
            'message' => [
                'message_id' => 54,
                'from' => ['id' => 123, 'first_name' => 'Test', 'is_bot' => false],
                'chat' => ['id' => 123, 'type' => 'private'],
                'date' => 1700000000,
                'text' => '19:30',
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

    public function testCallbackQueryWithInlineMessageIdGoesToGameQueueByGameId(): void
    {
        $this->db->insert('games', ['title' => 'Test', 'created_by' => 1, 'inline_query_id' => 'q1']);
        $gameId = (int) $this->db->id();
        $this->db->insert('game_inline_messages', ['game_id' => $gameId, 'inline_message_id' => 'inline_msg_abc']);

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

        $this->assertEnqueuedOnce('game_' . $gameId);
    }

    protected function setUp(): void
    {
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
        $this->router = new IncomingMessageQueueRouter(SpyQueue::class, self::BASE_DIR);
    }

    protected function tearDown(): void
    {
        Connection::close();
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
