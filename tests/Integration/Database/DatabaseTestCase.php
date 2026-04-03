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
        ?string $inlineMessageId = 'msg_1',
        ?int $chatId = null,
        ?int $messageId = null,
    ): int {
        $data = [
            'title' => $title,
            'created_by' => $createdBy,
        ];

        if (null !== $inlineMessageId) {
            $data['inline_message_id'] = $inlineMessageId;
        }
        if (null !== $chatId) {
            $data['chat_id'] = $chatId;
        }
        if (null !== $messageId) {
            $data['message_id'] = $messageId;
        }

        $this->db->insert('games', $data);

        return (int) $this->db->id();
    }

    protected function createParticipant(
        int $gameId,
        int $telegramId = 200,
        string $firstName = 'Danil',
        ?string $lastName = null,
        ?string $username = null,
    ): int {
        $this->db->insert('participants', [
            'game_id' => $gameId,
            'telegram_id' => $telegramId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
        ]);

        return (int) $this->db->id();
    }
}