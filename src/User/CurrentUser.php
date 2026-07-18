<?php

declare(strict_types=1);

namespace BeachVolleybot\User;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\UserRepository;

final readonly class CurrentUser
{
    private function __construct(
        private Role $role,
    ) {
    }

    public static function fromTelegramId(int $telegramUserId): self
    {
        $roleId = new UserRepository(Connection::get())->findRoleById($telegramUserId);
        $role = null === $roleId ? null : Role::tryFrom($roleId);

        return new self($role ?? Role::Player);
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function isRoot(): bool
    {
        return $this->role->isRoot();
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function hasAtLeast(Role $required): bool
    {
        return $this->role->isAtLeast($required);
    }
}
