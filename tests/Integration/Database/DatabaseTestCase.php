<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\User\Role;
use Medoo\Medoo;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected Medoo $db;

    protected function setUp(): void
    {
        $this->db = new Medoo([
            'type' => 'sqlite',
            'database' => ':memory:',
            'error' => PDO::ERRMODE_EXCEPTION,
            'command' => [
                'PRAGMA foreign_keys = ON',
            ],
        ]);

        $this->applyMigration('001_create_games_and_participants.sql');
        $this->applyMigration('004_split_game_inline_messages.sql');
        $this->applyMigration('005_require_game_player_time.sql');
        $this->applyMigration('006_rename_players_to_users.sql');
        $this->applyMigration('007_add_role_to_users.sql');
        $this->applyMigration('008_rename_inline_query_id_to_game_key.sql');
        $this->applyMigration('009_add_game_chat_messages.sql');
    }

    protected function createGame(
        string $title = 'Friday Game 18:00',
        int $createdBy = 100,
        string $inlineMessageId = 'msg_1',
        string $gameKey = 'query_1',
    ): int {
        $this->db->insert('games', [
            'title' => $title,
            'created_by' => $createdBy,
            'game_key' => $gameKey,
        ]);
        $gameId = (int) $this->db->id();

        $this->attachInlineMessage($gameId, $inlineMessageId);

        return $gameId;
    }

    protected function attachInlineMessage(int $gameId, string $inlineMessageId): void
    {
        $this->db->insert('game_inline_messages', [
            'game_id' => $gameId,
            'inline_message_id' => $inlineMessageId,
        ]);
    }

    protected function createUser(
        int $telegramUserId = 200,
        string $firstName = 'Danil',
        ?string $lastName = null,
        ?string $username = null,
        int $role = Role::Player->value,
    ): void {
        $this->db->pdo->prepare(
            'INSERT INTO users (telegram_user_id, first_name, last_name, username, role)
             VALUES (:telegram_user_id, :first_name, :last_name, :username, :role)
             ON CONFLICT (telegram_user_id) DO NOTHING'
        )->execute([
            ':telegram_user_id' => $telegramUserId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':username' => $username,
            ':role' => $role,
        ]);
    }

    protected function createGameUser(
        int $gameId,
        int $telegramUserId = 200,
        string $time = '18:00',
    ): void {
        $this->createUser($telegramUserId);
        $this->db->insert('game_users', [
            'game_id' => $gameId,
            'telegram_user_id' => $telegramUserId,
            'time' => $time,
        ]);
    }

    private function applyMigration(string $filename): void
    {
        $sql = file_get_contents(__DIR__ . '/../../../migrations/' . $filename);
        $this->db->pdo->exec($sql);
    }
}
