<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\User\Role;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;
use Medoo\Medoo;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    /** Stands in for titles that carry no resolvable kickoff, which games.kickoff_at no longer allows. */
    private const string FALLBACK_KICKOFF_AT = '2099-12-31 18:00:00';

    protected Medoo $db;

    protected function setUp(): void
    {
        $this->db = new Medoo([
            'type' => 'sqlite',
            'database' => ':memory:',
            'error' => PDO::ERRMODE_EXCEPTION,
            // Keeps every statement, so tests can assert what did — and did not — hit the DB.
            'logging' => true,
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
        $this->applyMigration('010_add_kickoff_at_and_venue_name.sql');
        $this->applyMigration('011_require_kickoff_at.sql');
    }

    /**
     * SQL statements the given action ran, seeding done beforehand excluded.
     *
     * @return string[]
     */
    protected function queriesDuring(callable $action): array
    {
        $before = count($this->db->log());
        $action();

        return array_slice($this->db->log(), $before);
    }

    protected function createGame(
        string $title = 'Friday Game 18:00',
        int $createdBy = 100,
        string $inlineMessageId = 'msg_1',
        string $gameKey = 'query_1',
        ?string $kickoffAt = null,
    ): int {
        $this->db->insert('games', [
            'title' => $title,
            'created_by' => $createdBy,
            'game_key' => $gameKey,
            'kickoff_at' => $kickoffAt ?? $this->resolveKickoffAt($title),
            'venue_name' => KnownVenues::findInTitle($title)?->name,
        ]);
        $gameId = (int) $this->db->id();

        $this->attachInlineMessage($gameId, $inlineMessageId);

        return $gameId;
    }

    protected function resolveKickoffAt(string $title): string
    {
        return GameDateTimeResolver::resolve($title, new DateTimeImmutable())?->format('Y-m-d H:i:s')
            ?? self::FALLBACK_KICKOFF_AT;
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
