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
    }

    protected function tearDown(): void
    {
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
}