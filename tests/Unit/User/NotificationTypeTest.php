<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\User;

use BeachVolleybot\User\NotificationType;
use PHPUnit\Framework\TestCase;

final class NotificationTypeTest extends TestCase
{
    public function testBitsStayWhereUsersNotificationsStoresThem(): void
    {
        $this->assertSame(
            ['GameReachedMinimumPlayers' => 2, 'GameShortBeforeKickoff' => 4, 'PromotedIntoGame' => 8, 'BumpedFromGame' => 16],
            array_combine(
                array_map(static fn(NotificationType $type): string => $type->name, NotificationType::cases()),
                array_map(static fn(NotificationType $type): int => $type->bit(), NotificationType::cases()),
            ),
        );
    }
}
