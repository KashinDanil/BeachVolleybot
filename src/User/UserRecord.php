<?php

declare(strict_types=1);

namespace BeachVolleybot\User;

use BeachVolleybot\Database\Timestamp;
use DateTimeImmutable;

readonly class UserRecord
{
    public function __construct(
        public int $telegramUserId,
        public string $firstName,
        public ?string $lastName,
        public ?string $username,
        public Role $role,
        public NotificationSettings $notifications,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int)$row['telegram_user_id'],
            (string)$row['first_name'],
            $row['last_name'] ?? null,
            $row['username'] ?? null,
            Role::from((int)$row['role']),
            NotificationSettings::fromInt((int)$row['notifications']),
            Timestamp::parse((string)$row['created_at']),
            Timestamp::parse((string)$row['updated_at']),
        );
    }
}
