<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use Medoo\Medoo;
use PDOStatement;
use Throwable;

/** Single-value reads and query() never leave Medoo holding an open read, which would pin a WAL snapshot. */
final class ConcurrentSqliteMedoo extends Medoo
{
    public function query(string $statement, array $map = []): ?PDOStatement
    {
        return $this->withStatementReleased(parent::query($statement, $map));
    }

    public function get(string $table, $join = null, $columns = null, $where = null)
    {
        return $this->withStatementReleased(parent::get($table, $join, $columns, $where));
    }

    public function has(string $table, $join, $where = null): bool
    {
        return $this->withStatementReleased(parent::has($table, $join, $where));
    }

    public function count(string $table, $join = null, $column = null, $where = null): ?int
    {
        return $this->withStatementReleased(parent::count($table, $join, $column, $where));
    }

    public function avg(string $table, $join, $column = null, $where = null): ?string
    {
        return $this->withStatementReleased(parent::avg($table, $join, $column, $where));
    }

    public function max(string $table, $join, $column = null, $where = null): ?string
    {
        return $this->withStatementReleased(parent::max($table, $join, $column, $where));
    }

    public function min(string $table, $join, $column = null, $where = null): ?string
    {
        return $this->withStatementReleased(parent::min($table, $join, $column, $where));
    }

    public function sum(string $table, $join, $column = null, $where = null): ?string
    {
        return $this->withStatementReleased(parent::sum($table, $join, $column, $where));
    }

    public function action(callable $actions): void
    {
        // Drops Medoo's own leftover statement, such as a SELECT run through exec().
        $this->statement = null;
        // IMMEDIATE takes the write lock up front, so busy_timeout also covers a read-then-write callback.
        $this->pdo->exec('BEGIN IMMEDIATE');

        try {
            $commitRequested = false !== $actions($this);
            $this->pdo->exec($commitRequested ? 'COMMIT' : 'ROLLBACK');
        } catch (Throwable $throwable) {
            $this->rollBack();

            throw $throwable;
        }
    }

    private function withStatementReleased(mixed $result): mixed
    {
        $this->statement = null;

        return $result;
    }

    private function rollBack(): void
    {
        try {
            $this->pdo->exec('ROLLBACK');
        } catch (Throwable) {
            // SQLite may have rolled back already, and the caller needs the original error either way.
        }
    }
}
