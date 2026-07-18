<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\User;

use BeachVolleybot\User\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function testRootSatisfiesEveryRequirement(): void
    {
        $this->assertTrue(Role::Root->isAtLeast(Role::Root));
        $this->assertTrue(Role::Root->isAtLeast(Role::Admin));
        $this->assertTrue(Role::Root->isAtLeast(Role::Player));
    }

    public function testAdminSatisfiesAdminAndPlayerButNotRoot(): void
    {
        $this->assertFalse(Role::Admin->isAtLeast(Role::Root));
        $this->assertTrue(Role::Admin->isAtLeast(Role::Admin));
        $this->assertTrue(Role::Admin->isAtLeast(Role::Player));
    }

    public function testPlayerSatisfiesOnlyPlayer(): void
    {
        $this->assertFalse(Role::Player->isAtLeast(Role::Root));
        $this->assertFalse(Role::Player->isAtLeast(Role::Admin));
        $this->assertTrue(Role::Player->isAtLeast(Role::Player));
    }
}
