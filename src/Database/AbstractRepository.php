<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use Medoo\Medoo;

abstract readonly class AbstractRepository
{
    public function __construct(
        protected Medoo $db,
    ) {
    }

    abstract protected function table(): string;

    abstract protected function primaryKeyColumn(): string;

    public function findById(int $id): ?array
    {
        return $this->db->get($this->table(), '*', [$this->primaryKeyColumn() => $id]) ?: null;
    }

    public function delete(int $id): bool
    {
        $result = $this->db->delete($this->table(), [$this->primaryKeyColumn() => $id]);

        return 0 < $result->rowCount();
    }
}