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
        ?NotificationSettings $notifications = new NotificationSettings(),
        ?string $languageCode = null,
        DateTimeImmutable $createdAt = new DateTimeImmutable('2026-01-01 10:00:00'),
        DateTimeImmutable $updatedAt = new DateTimeImmutable('2026-01-01 10:00:00'),
    ): UserRecord {
        return new UserRecord(
            telegramUserId: $telegramUserId,
            firstName: $firstName,
            lastName: $lastName,
            username: $username,
            languageCode: $languageCode,
            role: $role,
            notifications: $notifications,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }
}
