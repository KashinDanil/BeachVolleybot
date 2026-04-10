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

        $schema = file_get_contents(__DIR__ . '/../../../migrations/001_create_games_and_participants.sql');
        $this->db->pdo->exec($schema);
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
            'inline_message_id' => $inlineMessageId,
            'inline_query_id' => $inlineQueryId,
        ]);

        return (int) $this->db->id();
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
}