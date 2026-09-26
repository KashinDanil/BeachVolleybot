<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\User;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\User\UserRecord;
use RuntimeException;

final class UserManagerTest extends DatabaseTestCase
{
    private UserManager $userManager;

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        $this->userManager = new UserManager();
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testFindUserRecordByIdReturnsNullForAnUnknownId(): void
    {
        $this->assertNull($this->userManager->findUserRecordById(200));
    }

    public function testFindUserRecordByIdReturnsAPopulatedRecordForASeededUser(): void
    {
        $this->createUser(telegramUserId: 200, firstName: 'Danil');

        $record = $this->userManager->findUserRecordById(200);

        $this->assertNotNull($record);
        $this->assertSame(200, $record->telegramUserId);
        $this->assertSame('Danil', $record->firstName);
    }

    public function testEnableNotificationThenReReadShowsItEnabled(): void
    {
        $this->createUser(telegramUserId: 200);

        $this->userManager->enableNotification($this->currentUser(200), NotificationType::PromotedIntoGame);

        $record = $this->userManager->findUserRecordById(200);
        $this->assertTrue($record?->notifications->isEnabled(NotificationType::PromotedIntoGame));
        $this->assertSame(1 << NotificationType::PromotedIntoGame->value, $record->notifications->toInt());
    }

    public function testTogglingASecondTypeOrsInWithoutDroppingTheFirst(): void
    {
        $this->createUser(telegramUserId: 200);

        $this->userManager->enableNotification($this->currentUser(200), NotificationType::PromotedIntoGame);
        $this->userManager->enableNotification($this->currentUser(200), NotificationType::BumpedFromGame);

        $record = $this->userManager->findUserRecordById(200);
        $this->assertTrue($record?->notifications->isEnabled(NotificationType::PromotedIntoGame));
        $this->assertTrue($record->notifications->isEnabled(NotificationType::BumpedFromGame));
        $this->assertSame(
            (1 << NotificationType::PromotedIntoGame->value) | (1 << NotificationType::BumpedFromGame->value),
            $record->notifications->toInt(),
        );
    }

    public function testDisableNotificationClearsIt(): void
    {
        $this->createUser(telegramUserId: 200);
        $this->userManager->enableNotification($this->currentUser(200), NotificationType::PromotedIntoGame);

        $this->userManager->disableNotification($this->currentUser(200), NotificationType::PromotedIntoGame);

        $record = $this->userManager->findUserRecordById(200);
        $this->assertFalse($record?->notifications->isEnabled(NotificationType::PromotedIntoGame));
    }

    public function testDisableNotificationLeavesOtherBitsAlone(): void
    {
        $this->createUser(telegramUserId: 200);
        $this->userManager->enableNotification($this->currentUser(200), NotificationType::PromotedIntoGame);
        $this->userManager->enableNotification($this->currentUser(200), NotificationType::BumpedFromGame);

        $this->userManager->disableNotification($this->currentUser(200), NotificationType::PromotedIntoGame);

        $record = $this->userManager->findUserRecordById(200);
        $this->assertFalse($record?->notifications->isEnabled(NotificationType::PromotedIntoGame));
        $this->assertTrue($record->notifications->isEnabled(NotificationType::BumpedFromGame));
    }

    private function currentUser(int $telegramUserId): UserRecord
    {
        return $this->userManager->findUserRecordById($telegramUserId)
            ?? throw new RuntimeException("No user record found for $telegramUserId");
    }
}
