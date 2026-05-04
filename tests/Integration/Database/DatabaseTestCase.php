<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

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
    }

    protected function createGame(
        string $title = 'Friday Game',
        int $createdBy = 100,
        string $inlineMessageId = 'msg_1',
        string $inlineQueryId = 'query_1',
    ): int {
        $this->db->insert('games', [
            'title' => $title,
            'created_by' => $createdBy,
            'inline_query_id' => $inlineQueryId,
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

    protected function createPlayer(
        int $telegramUserId = 200,
        string $firstName = 'Danil',
        ?string $lastName = null,
        ?string $username = null,
    ): void {
        $this->db->pdo->prepare(
            'INSERT INTO players (telegram_user_id, first_name, last_name, username)
             VALUES (:telegram_user_id, :first_name, :last_name, :username)
             ON CONFLICT (telegram_user_id) DO NOTHING'
        )->execute([
            ':telegram_user_id' => $telegramUserId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':username' => $username,
        ]);
    }

    protected function createGamePlayer(
        int $gameId,
        int $telegramUserId = 200,
        ?string $time = null,
    ): void {
        $this->createPlayer($telegramUserId);
        $this->db->insert('game_players', [
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
