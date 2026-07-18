<?php

declare(strict_types=1);

namespace BeachVolleybot\User;

enum Role: int
{
    // Backing values are stable storage identifiers persisted in users.role,
    // laid out in ascending privilege order from 0. The authoritative order
    // still lives in rank(): to slot a new role mid-hierarchy, add its case
    // here and renumber rank() — never compare the backing values directly.
    case Player = 0;
    case Admin = 1;
    case Root = 2;

    public function isAdmin(): bool
    {
        return $this->isAtLeast(self::Admin);
    }

    public function isRoot(): bool
    {
        return $this->isAtLeast(self::Root);
    }

    public function isAtLeast(self $minimumRole): bool
    {
        return $this->rank() >= $minimumRole->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Player => 0,
            self::Admin => 1,
            self::Root => 2,
        };
    }
}
