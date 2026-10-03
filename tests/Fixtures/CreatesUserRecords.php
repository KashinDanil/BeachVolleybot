<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Fixtures;

use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserRecord;
use DateTimeImmutable;

trait CreatesUserRecords
{
    protected function userRecord(
        int $telegramUserId = 100,
        string $firstName = 'Alice',
        ?string $lastName = null,
        ?string $username = null,
        Role $role = Role::Player,
    ): UserRecord {
        return new UserRecord(
            telegramUserId: $telegramUserId,
            firstName: $firstName,
            lastName: $lastName,
            username: $username,
            languageCode: null,
            role: $role,
            notifications: new NotificationSettings(),
            createdAt: new DateTimeImmutable('2026-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-01-01 10:00:00'),
        );
    }
}
