<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\Migrator;
use Medoo\Medoo;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigratorTest extends TestCase
{
    private const string REAL_MIGRATIONS_DIR = __DIR__ . '/../../../migrations';

    private string $migrationsDir;
    private Medoo $db;

    protected function setUp(): void
    {
        $this->migrationsDir = sys_get_temp_dir() . '/bvb_test_migrations_' . uniqid('', true);
        mkdir($this->migrationsDir, 0777, true);

        $this->db = new Medoo([
            'type' => 'sqlite',
            'database' => ':memory:',
            'error' => PDO::ERRMODE_EXCEPTION,
        ]);

        ob_start();
    }

    protected function tearDown(): void
    {
        ob_end_clean();

        foreach (glob($this->migrationsDir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->migrationsDir);
    }

    public function testRunReturnsZeroWhenNoMigrations(): void
    {
        $migrator = new Migrator($this->migrationsDir, $this->db);

        $this->assertSame(0, $migrator->run());
    }

    public function testRunAppliesMigration(): void
    {
        file_put_contents(
            $this->migrationsDir . '/001_test.sql',
            'CREATE TABLE test (id INTEGER PRIMARY KEY)',
        );

        $migrator = new Migrator($this->migrationsDir, $this->db);
        $count = $migrator->run();

        $this->assertSame(1, $count);

        $tables = $this->db->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('test', $tables);
    }

    public function testRunSkipsAlreadyAppliedMigrations(): void
    {
        file_put_contents(
            $this->migrationsDir . '/001_test.sql',
            'CREATE TABLE test (id INTEGER PRIMARY KEY)',
        );

        $migrator = new Migrator($this->migrationsDir, $this->db);
        $migrator->run();
        $count = $migrator->run();

        $this->assertSame(0, $count);
    }

    public function testRunAppliesMultipleMigrationsInOrder(): void
    {
        file_put_contents(
            $this->migrationsDir . '/001_first.sql',
            'CREATE TABLE first (id INTEGER PRIMARY KEY)',
        );
        file_put_contents(
            $this->migrationsDir . '/002_second.sql',
            'CREATE TABLE second (id INTEGER PRIMARY KEY)',
        );

        $migrator = new Migrator($this->migrationsDir, $this->db);
        $count = $migrator->run();

        $this->assertSame(2, $count);
    }

    public function testRunRecordsMigrationFilenames(): void
    {
        file_put_contents(
            $this->migrationsDir . '/001_test.sql',
            'CREATE TABLE test (id INTEGER PRIMARY KEY)',
        );

        $migrator = new Migrator($this->migrationsDir, $this->db);
        $migrator->run();

        $applied = $this->db->select('applied_migrations', 'filename');
        $this->assertSame(['001_test.sql'], $applied);
    }

    public function testRunThrowsOnInvalidMigrationsDir(): void
    {
        $this->expectException(RuntimeException::class);

        $migrator = new Migrator('/nonexistent/path', $this->db);
        $migrator->run();
    }

    public function testRunRollsBackFailedMigration(): void
    {
        file_put_contents(
            $this->migrationsDir . '/001_bad.sql',
            'INVALID SQL STATEMENT',
        );

        $migrator = new Migrator($this->migrationsDir, $this->db);

        $this->expectException(RuntimeException::class);
        $migrator->run();
    }

    public function testRequireGamePlayerTimeMigrationPreservesSlots(): void
    {
        $this->db->pdo->exec('PRAGMA foreign_keys = ON');

        $this->copyRealMigration('001_create_games_and_participants.sql');
        $this->copyRealMigration('004_split_game_inline_messages.sql');

        $migrator = new Migrator($this->migrationsDir, $this->db);
        $migrator->run();

        $this->insertGameWithPlayerAndSlot();

        $this->copyRealMigration('005_require_game_player_time.sql');
        $this->assertSame(1, $migrator->run());

        $gamePlayer = $this->db->get('game_players', '*', [
            'game_id' => 1,
            'telegram_user_id' => 200,
        ]);
        $this->assertSame('18:00', $gamePlayer['time']);

        $slots = $this->db->select('game_slots', '*', ['game_id' => 1]);
        $this->assertCount(1, $slots);
        $this->assertSame(200, (int)$slots[0]['telegram_user_id']);
        $this->assertSame(1, (int)$slots[0]['position']);

        $columns = $this->db->pdo->query('PRAGMA table_info(game_players)')->fetchAll(PDO::FETCH_ASSOC);
        $timeColumn = array_values(array_filter($columns, fn (array $column) => 'time' === $column['name']))[0] ?? null;
        $this->assertNotNull($timeColumn);
        $this->assertSame(1, (int)$timeColumn['notnull']);
    }

    public function testRenamePlayersToUsersMigrationPreservesData(): void
    {
        $this->db->pdo->exec('PRAGMA foreign_keys = ON');

        $this->copyRealMigration('001_create_games_and_participants.sql');
        $this->copyRealMigration('004_split_game_inline_messages.sql');
        $this->copyRealMigration('005_require_game_player_time.sql');

        $migrator = new Migrator($this->migrationsDir, $this->db);
        $migrator->run();

        // Seed pre-rename data (tables are still players / game_players here).
        $this->db->insert('games', [
            'game_id' => 1,
            'inline_query_id' => 'query_1',
            'title' => 'Friday Game 18:00',
            'created_by' => 200,
        ]);
        $this->db->insert('game_inline_messages', ['game_id' => 1, 'inline_message_id' => 'msg_1']);
        // Explicit timestamps let us prove they survive the rebuild verbatim.
        $this->db->pdo->exec(
            "INSERT INTO players (telegram_user_id, first_name, last_name, username, created_at, updated_at)
             VALUES (200, 'Danil', 'K', 'danil', '2020-01-01T00:00:00Z', '2020-01-02T00:00:00Z')"
        );
        $this->db->insert('players', ['telegram_user_id' => 201, 'first_name' => 'Alex']);
        $this->db->insert('game_players', ['game_id' => 1, 'telegram_user_id' => 200, 'time' => '18:00', 'volleyball' => 2, 'net' => 1]);
        $this->db->insert('game_players', ['game_id' => 1, 'telegram_user_id' => 201, 'time' => '19:00', 'volleyball' => 0, 'net' => 0]);
        $this->db->insert('game_slots', ['game_id' => 1, 'telegram_user_id' => 200, 'position' => 1]);
        $this->db->insert('game_slots', ['game_id' => 1, 'telegram_user_id' => 201, 'position' => 2]);

        // Apply the rename migration.
        $this->copyRealMigration('006_rename_players_to_users.sql');
        $this->assertSame(1, $migrator->run());

        // Old tables are gone; new tables exist.
        $tables = $this->db->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertNotContains('players', $tables);
        $this->assertNotContains('game_players', $tables);
        $this->assertContains('users', $tables);
        $this->assertContains('game_users', $tables);

        // Row counts are preserved exactly.
        $this->assertSame(2, (int)$this->db->count('users'));
        $this->assertSame(2, (int)$this->db->count('game_users'));
        $this->assertSame(2, (int)$this->db->count('game_slots'));

        // Values (including timestamps) survive verbatim.
        $user = $this->db->get('users', '*', ['telegram_user_id' => 200]);
        $this->assertSame('Danil', $user['first_name']);
        $this->assertSame('danil', $user['username']);
        $this->assertSame('2020-01-01T00:00:00Z', $user['created_at']);
        $this->assertSame('2020-01-02T00:00:00Z', $user['updated_at']);

        $gameUser = $this->db->get('game_users', '*', ['game_id' => 1, 'telegram_user_id' => 200]);
        $this->assertSame('18:00', $gameUser['time']);
        $this->assertSame(2, (int)$gameUser['volleyball']);
        $this->assertSame(1, (int)$gameUser['net']);

        // Foreign keys are intact after the rebuild.
        $violations = $this->db->pdo->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame([], $violations);

        // Cascade still works through the renamed tables.
        $this->db->delete('users', ['telegram_user_id' => 200]);
        $this->assertSame(1, (int)$this->db->count('game_users'));
        $this->assertSame(1, (int)$this->db->count('game_slots'));
        $this->assertFalse($this->db->has('game_users', ['telegram_user_id' => 200]));
    }

    private function copyRealMigration(string $filename): void
    {
        copy(self::REAL_MIGRATIONS_DIR . '/' . $filename, $this->migrationsDir . '/' . $filename);
    }

    private function insertGameWithPlayerAndSlot(): void
    {
        $this->db->insert('games', [
            'game_id' => 1,
            'inline_query_id' => 'query_1',
            'title' => 'Friday Game 18:00',
            'created_by' => 200,
        ]);
        $this->db->insert('game_inline_messages', [
            'game_id' => 1,
            'inline_message_id' => 'msg_1',
        ]);
        $this->db->insert('players', [
            'telegram_user_id' => 200,
            'first_name' => 'Danil',
        ]);
        $this->db->insert('game_players', [
            'game_id' => 1,
            'telegram_user_id' => 200,
            'time' => '18:00',
            'volleyball' => 1,
            'net' => 1,
        ]);
        $this->db->insert('game_slots', [
            'game_id' => 1,
            'telegram_user_id' => 200,
            'position' => 1,
        ]);
    }
}
