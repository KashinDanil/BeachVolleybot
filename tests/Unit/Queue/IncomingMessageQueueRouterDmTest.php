<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Queue;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Processors\ProcessorRegistryFactory;
use BeachVolleybot\Routing\IncomingMessageQueueRouter;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use BeachVolleybot\User\Role;
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

    public function testUnrecognizedPrivateTextIsNotRouted(): void
    {
        $this->router->route($this->privateMessageUpdate(12345, 'hello'));

        $this->assertSame([], SpyQueue::$instances);
    }

    public function testNonInlineAdminCallbackQueryRoutesToDmQueue(): void
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
                'data' => '{"aa":"st"}',
            ],
        ]);

        $this->router->route($update);

        $this->assertEnqueuedOnce('dm_12345678');
    }

    public function testGamesCallbackByAdminRoutesToDmQueue(): void
    {
        $this->router->route($this->dmCallbackUpdate(12345678, '{"aa":"gl"}'));

        $this->assertEnqueuedOnce('dm_12345678');
    }

    public function testLogsCallbackByAdminRoutesToDmQueue(): void
    {
        // An admin is still routed (they can be in the admin UI); the root-only
        // restriction is enforced at processing time, which answers the callback
        // instead of silently dropping it and hanging the client spinner.
        $this->router->route($this->dmCallbackUpdate(12345678, '{"aa":"lgs"}'));

        $this->assertEnqueuedOnce('dm_12345678');
    }

    public function testLogsCallbackByRootRoutesToDmQueue(): void
    {
        $this->router->route($this->dmCallbackUpdate(87654321, '{"aa":"lgs"}'));

        $this->assertEnqueuedOnce('dm_87654321');
    }

    public function testPrivateReplyToViaBotGameMessageRoutesToGameQueueByGameId(): void
    {
        $this->db->insert('games', ['title' => 'Test', 'created_by' => 1, 'game_key' => 'query_dm']);
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
        $this->db->insert('games', ['title' => 'Test', 'created_by' => 1, 'game_key' => 'q1']);
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
        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../migrations/005_require_game_player_time.sql'));
        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../migrations/006_rename_players_to_users.sql'));
        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../migrations/007_add_role_to_users.sql'));
        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../migrations/008_rename_inline_query_id_to_game_key.sql'));
        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../migrations/009_add_game_chat_messages.sql'));
        Connection::set($this->db);

        // Admin routing reads the role from the DB; seed the admin sender used
        // by the admin fixtures below.
        $this->db->insert('users', [
            'telegram_user_id' => 12345678,
            'first_name' => 'Admin',
            'role' => Role::Admin->value,
        ]);

        // Logs actions are root-only; seed a root sender for those fixtures.
        $this->db->insert('users', [
            'telegram_user_id' => 87654321,
            'first_name' => 'Root',
            'role' => Role::Root->value,
        ]);

        SpyQueue::reset();
        $this->router = new IncomingMessageQueueRouter(SpyQueue::class, self::BASE_DIR, ProcessorRegistryFactory::createQueued());
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    private function dmCallbackUpdate(int $userId, string $data): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 100,
            'callback_query' => [
                'id' => 'cbq_1',
                'from' => ['id' => $userId, 'first_name' => 'Danil', 'is_bot' => false],
                'chat_instance' => '-123',
                'message' => [
                    'message_id' => 109,
                    'from' => ['id' => 999, 'first_name' => 'Bot', 'is_bot' => true],
                    'chat' => ['id' => $userId, 'type' => 'private'],
                    'date' => 1700000000,
                    'text' => 'Settings',
                ],
                'data' => $data,
            ],
        ]);
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
