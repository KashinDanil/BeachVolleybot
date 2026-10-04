<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Processors\UserProcessors;

use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Processors\UserProcessors\UserDisableNotificationCallbackProcessor;
use BeachVolleybot\Processors\UserProcessors\UserEnableNotificationCallbackProcessor;
use BeachVolleybot\Processors\UserProcessors\UserGameDetailCallbackProcessor;
use BeachVolleybot\Processors\UserProcessors\UserGamesListCallbackProcessor;
use BeachVolleybot\Processors\UserProcessors\UserNotificationDetailCallbackProcessor;
use BeachVolleybot\Processors\UserProcessors\UserNotificationsListCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use BeachVolleybot\User\NotificationType;
use PHPUnit\Framework\TestCase;

final class UserCallbackActionTest extends TestCase
{
    use CreatesUserRecords;

    private TelegramMessageSender $telegramSender;

    public function testResolvesTheGamesScreens(): void
    {
        $mapping = [
            [UserCallbackAction::GamesList, UserGamesListCallbackProcessor::class],
            [UserCallbackAction::GameDetail, UserGameDetailCallbackProcessor::class],
        ];

        foreach ($mapping as [$action, $expectedClass]) {
            $callbackData = UserCallbackData::create($action);

            $this->assertEquals(
                new $expectedClass($this->telegramSender, $callbackData),
                $action->resolveProcessor($this->telegramSender, $callbackData, $this->userRecord()),
                "Failed for action '$action->value'",
            );
        }
    }

    public function testHandsTheSenderToTheNotificationsList(): void
    {
        $userRecord = $this->userRecord();
        $callbackData = UserCallbackData::create(UserCallbackAction::NotificationsList);

        $this->assertEquals(
            new UserNotificationsListCallbackProcessor($this->telegramSender, $userRecord),
            UserCallbackAction::NotificationsList->resolveProcessor($this->telegramSender, $callbackData, $userRecord),
        );
    }

    public function testHandsTheSenderToTheNotificationScreens(): void
    {
        $userRecord = $this->userRecord();
        $mapping = [
            [UserCallbackAction::NotificationDetail, UserNotificationDetailCallbackProcessor::class],
            [UserCallbackAction::EnableNotification, UserEnableNotificationCallbackProcessor::class],
            [UserCallbackAction::DisableNotification, UserDisableNotificationCallbackProcessor::class],
        ];

        foreach ($mapping as [$action, $expectedClass]) {
            $callbackData = UserCallbackData::create($action)->withNotificationType(NotificationType::BumpedFromGame);

            $this->assertEquals(
                new $expectedClass($this->telegramSender, $callbackData, $userRecord),
                $action->resolveProcessor($this->telegramSender, $callbackData, $userRecord),
                "Failed for action '$action->value'",
            );
        }
    }

    protected function setUp(): void
    {
        $this->telegramSender = $this->createStub(TelegramMessageSender::class);
    }
}
