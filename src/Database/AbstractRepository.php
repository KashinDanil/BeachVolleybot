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

    public function findById(int $id): ?array
    {
        return $this->db->get($this->table(), '*', ['id' => $id]) ?: null;
    }

    public function delete(int $id): bool
    {
        $result = $this->db->delete($this->table(), ['id' => $id]);

        return 0 < $result->rowCount();
    }
}