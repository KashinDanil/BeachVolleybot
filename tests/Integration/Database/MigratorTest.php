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
