<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\AdminProcessors;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;

final class RootUserNotificationsProcessorsTest extends ProcessorTestCase
{
    private const int TARGET_ID = 300;
    private const string UNAVAILABLE = 'Notifications unavailable';

    // --- RootUserNotificationsListProcessor ---

    public function testListShowsTheUsersNotifications(): void
    {
        $this->createTarget(new NotificationSettings()->enable(NotificationType::BumpedFromGame));

        $this->process(AdminCallbackData::create(AdminCallbackAction::UserNotifications));

        $this->assertStringContainsString("*Notifications*\nUser: Alice\n", $this->editedText());
        $keyboard = $this->lastKeyboard('editMessageText');
        $this->assertSame('success', $keyboard[NotificationType::BumpedFromGame->value - 1][0]['style'] ?? null);
        $this->assertArrayNotHasKey('style', $keyboard[NotificationType::PromotedIntoGame->value - 1][0]);
        $this->assertAnsweredWith('');
    }

    public function testListForAUserWhoNeverOptedInFallsBackToTheirDetails(): void
    {
        $this->createUser(self::TARGET_ID, 'Alice');

        $this->process(AdminCallbackData::create(AdminCallbackAction::UserNotifications));

        $this->assertStringContainsString('Notifications: —', $this->editedText());
        $this->assertNotContains('Notifications', $this->lastKeyboardLabels('editMessageText'));
        $this->assertAnsweredWith(self::UNAVAILABLE);
    }

    public function testListForAMissingUserFallsBackToUserNotFound(): void
    {
        $this->process(AdminCallbackData::create(AdminCallbackAction::UserNotifications));

        $this->assertStringContainsString('User not found', $this->editedText());
        $this->assertAnsweredWith(self::UNAVAILABLE);
    }

    // --- RootUserNotificationDetailProcessor ---

    public function testDetailShowsTheTypeForTheUser(): void
    {
        $this->createTarget(new NotificationSettings()->enable(NotificationType::PromotedIntoGame));

        $this->process($this->forType(AdminCallbackAction::UserNotificationDetail, NotificationType::PromotedIntoGame));

        $this->assertStringContainsString("User: Alice\n\n🔔 You'll get a notification when", $this->editedText());
        $this->assertSame('Disable', $this->lastKeyboard('editMessageText')[0][0]['text']);
        $this->assertAnsweredWith('');
    }

    public function testDetailForAUserWhoNeverOptedInFallsBackToTheirDetails(): void
    {
        $this->createUser(self::TARGET_ID, 'Alice');

        $this->process($this->forType(AdminCallbackAction::UserNotificationDetail, NotificationType::PromotedIntoGame));

        $this->assertStringContainsString('Notifications: —', $this->editedText());
        $this->assertAnsweredWith(self::UNAVAILABLE);
    }

    public function testDetailWithoutATypeOnlyAnswers(): void
    {
        $this->createTarget(new NotificationSettings());

        $this->process(AdminCallbackData::create(AdminCallbackAction::UserNotificationDetail));

        $this->assertMessageNotEdited();
        $this->assertAnsweredWith('');
    }

    // --- RootEnableUserNotificationProcessor / RootDisableUserNotificationProcessor ---

    public function testEnableStoresTheNotificationForTheUserNotTheRoot(): void
    {
        $this->seedRoot();
        $this->createTarget(new NotificationSettings());

        $this->process($this->forType(AdminCallbackAction::EnableUserNotification, NotificationType::PromotedIntoGame));

        $this->assertTrue($this->targetSettings()->isEnabled(NotificationType::PromotedIntoGame));
        $this->assertNull(new UserManager()->findUserRecordById(self::ADMIN_TELEGRAM_USER_ID)->notifications);
        $this->assertStringContainsString('User: Alice', $this->editedText());
        $this->assertSame('Disable', $this->lastKeyboard('editMessageText')[0][0]['text']);
        $this->assertAnsweredWith('Notification enabled');
    }

    public function testDisableClearsOnlyThatNotification(): void
    {
        $this->createTarget(
            new NotificationSettings()
                ->enable(NotificationType::PromotedIntoGame)
                ->enable(NotificationType::BumpedFromGame),
        );

        $this->process($this->forType(AdminCallbackAction::DisableUserNotification, NotificationType::PromotedIntoGame));

        $settings = $this->targetSettings();
        $this->assertFalse($settings->isEnabled(NotificationType::PromotedIntoGame));
        $this->assertTrue($settings->isEnabled(NotificationType::BumpedFromGame));
        $this->assertSame('Enable', $this->lastKeyboard('editMessageText')[0][0]['text']);
        $this->assertAnsweredWith('Notification disabled');
    }

    public function testSwitchForAUserWhoNeverOptedInChangesNothing(): void
    {
        $this->createUser(self::TARGET_ID, 'Alice');

        $this->process($this->forType(AdminCallbackAction::EnableUserNotification, NotificationType::PromotedIntoGame));

        $this->assertNull(new UserManager()->findUserRecordById(self::TARGET_ID)->notifications);
        $this->assertAnsweredWith(self::UNAVAILABLE);
    }

    public function testSwitchForAMissingUserStoresNothing(): void
    {
        $this->process($this->forType(AdminCallbackAction::EnableUserNotification, NotificationType::PromotedIntoGame));

        $this->assertNull(new UserManager()->findUserRecordById(self::TARGET_ID));
        $this->assertStringContainsString('User not found', $this->editedText());
        $this->assertAnsweredWith(self::UNAVAILABLE);
    }

    public function testEnableIsWrittenToTheAdminLog(): void
    {
        $this->createTarget(new NotificationSettings());

        $this->process($this->forType(AdminCallbackAction::EnableUserNotification, NotificationType::PromotedIntoGame));

        $this->assertStringContainsString(
            "id=12345678, name='Danil', username='', action='root_enable_user_notification', details='userId=300 notification=PromotedIntoGame'",
            $this->lastAdminLogLine(),
        );
    }

    public function testDisableIsWrittenToTheAdminLog(): void
    {
        $this->createTarget(new NotificationSettings()->enable(NotificationType::BumpedFromGame));

        $this->process($this->forType(AdminCallbackAction::DisableUserNotification, NotificationType::BumpedFromGame));

        $this->assertStringContainsString(
            "action='root_disable_user_notification', details='userId=300 notification=BumpedFromGame'",
            $this->lastAdminLogLine(),
        );
    }

    private function lastAdminLogLine(): string
    {
        $lines = file(BASE_LOG_DIR . '/admin_actions.log', FILE_IGNORE_NEW_LINES) ?: [];

        return (string)end($lines);
    }

    public function testSwitchWithoutATypeChangesNothing(): void
    {
        $this->createTarget(new NotificationSettings());

        $this->process(AdminCallbackData::create(AdminCallbackAction::EnableUserNotification));

        $this->assertSame(0, $this->targetSettings()->toInt());
        $this->assertMessageNotEdited();
        $this->assertAnsweredWith('');
    }

    private function createTarget(NotificationSettings $notifications): void
    {
        $this->createUser(self::TARGET_ID, 'Alice');
        $this->db->update(
            'users',
            ['notifications' => $notifications->toInt()],
            ['telegram_user_id' => self::TARGET_ID],
        );
    }

    private function forType(AdminCallbackAction $action, NotificationType $notificationType): AdminCallbackData
    {
        return AdminCallbackData::create($action)->withNotificationType($notificationType);
    }

    private function process(AdminCallbackData $callbackData): void
    {
        $callbackData = $callbackData->withUserId(self::TARGET_ID);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        $callbackData->getAction()
            ->resolveProcessor($this->telegramSender, $callbackData, $this->ensureSender($update))
            ->process($update);
    }

    private function targetSettings(): NotificationSettings
    {
        return new UserManager()->findUserRecordById(self::TARGET_ID)->effectiveNotifications();
    }
}
