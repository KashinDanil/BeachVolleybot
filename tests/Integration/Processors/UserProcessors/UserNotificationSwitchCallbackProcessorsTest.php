<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UserProcessors;

use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;

final class UserNotificationSwitchCallbackProcessorsTest extends ProcessorTestCase
{
    private const int SENDER_ID = 555;

    public function testEnablePersistsTheNotification(): void
    {
        $this->processSwitch(UserCallbackAction::EnableNotification, NotificationType::PromotedIntoGame);

        $this->assertTrue($this->storedSettings()->isEnabled(NotificationType::PromotedIntoGame));
    }

    private function processSwitch(UserCallbackAction $action, NotificationType $notificationType): void
    {
        $this->process(UserCallbackData::create($action)->withNotificationType($notificationType));
    }

    private function process(UserCallbackData $callbackData): void
    {
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload(
                data: $callbackData->toJson(),
                fromId: self::SENDER_ID,
                chatId: self::SENDER_ID,
            ),
        );

        $callbackData->getAction()->resolveProcessor($this->telegramSender, $callbackData)->process($update);
    }

    private function storedSettings(): NotificationSettings
    {
        return new UserManager()->findUserRecordById(self::SENDER_ID)->notifications;
    }

    public function testEnableRedrawsTheDetailWithDisableAndConfirms(): void
    {
        $this->processSwitch(UserCallbackAction::EnableNotification, NotificationType::PromotedIntoGame);

        $this->assertStringContainsString("🔔 You'll get a notification when", $this->editedText());
        $switchButton = $this->editedKeyboard()[0][0];
        $this->assertSame('Disable', $switchButton['text']);
        $this->assertSame('danger', $switchButton['style']);
        $this->assertAnsweredWith('Notification enabled');
    }

    private function editedKeyboard(): array
    {
        $editCalls = array_filter($this->bot->calls, fn($call) => 'editMessageText' === $call['method']);
        $this->assertNotEmpty($editCalls);

        return json_decode(end($editCalls)['args'][5]->toJson(), true)['inline_keyboard'];
    }

    public function testDisableClearsOnlyThatNotification(): void
    {
        $this->enable(NotificationType::PromotedIntoGame);
        $this->enable(NotificationType::BumpedFromGame);

        $this->processSwitch(UserCallbackAction::DisableNotification, NotificationType::PromotedIntoGame);

        $settings = $this->storedSettings();
        $this->assertFalse($settings->isEnabled(NotificationType::PromotedIntoGame));
        $this->assertTrue($settings->isEnabled(NotificationType::BumpedFromGame));
    }

    private function enable(NotificationType $notificationType): void
    {
        $this->createUser(telegramUserId: self::SENDER_ID);
        $userManager = new UserManager();
        $userManager->enableNotification($userManager->findUserRecordById(self::SENDER_ID), $notificationType);
    }

    public function testDisableRedrawsTheDetailWithEnableAndConfirms(): void
    {
        $this->enable(NotificationType::PromotedIntoGame);

        $this->processSwitch(UserCallbackAction::DisableNotification, NotificationType::PromotedIntoGame);

        $this->assertStringContainsString("🔕 You won't get a notification when", $this->editedText());
        $switchButton = $this->editedKeyboard()[0][0];
        $this->assertSame('Enable', $switchButton['text']);
        $this->assertSame('success', $switchButton['style']);
        $this->assertAnsweredWith('Notification disabled');
    }

    public function testEnablingAnAlreadyEnabledNotificationKeepsItEnabled(): void
    {
        $this->enable(NotificationType::PromotedIntoGame);

        $this->processSwitch(UserCallbackAction::EnableNotification, NotificationType::PromotedIntoGame);

        $this->assertSame(
            1 << NotificationType::PromotedIntoGame->value,
            $this->storedSettings()->toInt(),
        );
    }

    public function testCreatesAMissingUserBeforeSwitching(): void
    {
        $this->processSwitch(UserCallbackAction::EnableNotification, NotificationType::GameShortBeforeKickoff);

        $this->assertTrue($this->storedSettings()->isEnabled(NotificationType::GameShortBeforeKickoff));
    }

    public function testMissingNotificationTypeChangesNothing(): void
    {
        $this->createUser(telegramUserId: self::SENDER_ID);
        $callbackData = UserCallbackData::create(UserCallbackAction::EnableNotification);

        $this->process($callbackData);

        $this->assertMessageNotEdited();
        $this->assertAnsweredWith('');
        $this->assertSame(0, $this->storedSettings()->toInt());
    }
}
