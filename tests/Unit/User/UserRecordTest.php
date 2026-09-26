<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\User;

use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserRecordTest extends TestCase
{
    public function testFromRowHydratesEveryField(): void
    {
        $notificationsMask = (1 << NotificationType::GameReachedMinimumPlayers->value)
            | (1 << NotificationType::GameShortBeforeKickoff->value);

        $record = UserRecord::fromRow([
            'telegram_user_id' => '200',
            'first_name' => 'Danil',
            'last_name' => 'Kashin',
            'username' => 'kashindanil',
            'role' => (string)Role::Admin->value,
            'notifications' => (string)$notificationsMask,
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-02 11:00:00',
        ]);

        $this->assertSame(200, $record->telegramUserId);
        $this->assertSame('Danil', $record->firstName);
        $this->assertSame('Kashin', $record->lastName);
        $this->assertSame('kashindanil', $record->username);
        $this->assertSame(Role::Admin, $record->role);
        $this->assertTrue($record->notifications->isEnabled(NotificationType::GameReachedMinimumPlayers));
        $this->assertTrue($record->notifications->isEnabled(NotificationType::GameShortBeforeKickoff));
        $this->assertFalse($record->notifications->isEnabled(NotificationType::PromotedIntoGame));
        $this->assertEquals(new DateTimeImmutable('2026-01-01 10:00:00'), $record->createdAt);
        $this->assertEquals(new DateTimeImmutable('2026-01-02 11:00:00'), $record->updatedAt);
    }

    public function testFromRowKeepsLastNameAndUsernameNullWhenAbsent(): void
    {
        $record = UserRecord::fromRow([
            'telegram_user_id' => 200,
            'first_name' => 'Danil',
            'last_name' => null,
            'username' => null,
            'role' => Role::Player->value,
            'notifications' => 0,
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-01 10:00:00',
        ]);

        $this->assertNull($record->lastName);
        $this->assertNull($record->username);
    }
}
