<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use Medoo\Medoo;
use RuntimeException;

final readonly class Migrator
{
    public function __construct(
        private string $migrationsDir,
        private Medoo $db,
    ) {
    }

    public function run(): int
    {
        if (!is_dir($this->migrationsDir)) {
            throw new RuntimeException("Migrations directory does not exist: {$this->migrationsDir}");
        }

        $this->ensureMigrationsTable();

        $applied = $this->getAppliedMigrations();
        $pending = $this->getPendingMigrations($applied);

        if (empty($pending)) {
            $this->info('Nothing to migrate.', 'cyan');

            return 0;
        }

        $count = 0;

        foreach ($pending as $filename => $path) {
            $this->write("  Applying \033[33m$filename\033[0m ... ");

            $this->applyMigration($filename, $path);

            $this->info('OK', 'green');
            $count++;
        }

        $this->info("Applied $count migration(s).", 'green');

        return $count;
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS applied_migrations (
                filename TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
            )"
        );
    }

    /** @return list<string> */
    private function getAppliedMigrations(): array
    {
        return $this->db->select('applied_migrations', 'filename', ['ORDER' => ['filename' => 'ASC']]);
    }

    /** @return array<string, string> filename => full path */
    private function getPendingMigrations(array $applied): array
    {
        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);

        $pending = [];

        foreach ($files as $path) {
            $filename = basename($path);

            if (!in_array($filename, $applied, true)) {
                $pending[$filename] = $path;
            }
        }

        return $pending;
    }

    private function applyMigration(string $filename, string $path): void
    {
        $sql = file_get_contents($path);

        if (false === $sql) {
            throw new RuntimeException("Cannot read migration file: $path");
        }

        $this->db->action(function (Medoo $db) use ($sql, $filename) {
            $db->pdo->exec($sql);
            $db->insert('applied_migrations', ['filename' => $filename]);
        });
    }

    private function write(string $message): void
    {
        echo $message;
    }

    private function info(string $message, string $color): void
    {
        $codes = [
            'green' => '32',
            'red' => '31',
            'yellow' => '33',
            'cyan' => '36',
        ];

        $code = $codes[$color] ?? '0';

        echo "\033[$code" . "m$message\033[0m" . PHP_EOL;
    }
}
