<?php

declare(strict_types=1);

namespace BeachVolleybot\User;

enum Role: int
{
    case Root = 0;
    case Player = 1;
    case Admin = 2;

    public function isAdmin(): bool
    {
        return self::Admin === $this || self::Root === $this;
    }

    public function isRoot(): bool
    {
        return self::Root === $this;
    }
}
