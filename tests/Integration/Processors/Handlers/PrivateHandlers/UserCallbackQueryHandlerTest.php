<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserCallbackQueryHandler;
use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Processors\UserProcessors\UserNotificationsListCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;

final class UserCallbackQueryHandlerTest extends ProcessorTestCase
{
    private const int SENDER_ID = 555;

    public function testHandsTheSenderThroughTheCallbackAction(): void
    {
        $processor = new UserCallbackQueryHandler()->createProcessor(
            $this->telegramSender,
            $this->update(UserCallbackData::create(UserCallbackAction::NotificationsList)),
        );

        $this->assertEquals(
            new UserNotificationsListCallbackProcessor(
                $this->telegramSender,
                new UserManager()->findUserRecordById(self::SENDER_ID),
            ),
            $processor,
        );
    }

    public function testSwitchingCreatesAMissingUserAndStoresTheNotification(): void
    {
        $this->processThroughHandler($this->enable(NotificationType::GameShortBeforeKickoff));

        $notifications = new UserManager()->findUserRecordById(self::SENDER_ID)?->notifications;
        $this->assertTrue($notifications?->isEnabled(NotificationType::GameShortBeforeKickoff));
    }

    public function testSwitchingCostsTheUpsertAndTheUpdate(): void
    {
        $this->createUser(telegramUserId: self::SENDER_ID);

        $queries = $this->queriesDuring(
            fn() => $this->processThroughHandler($this->enable(NotificationType::PromotedIntoGame)),
        );

        $this->assertCount(2, $queries);
        $this->assertStringContainsString('INSERT INTO users', $queries[0]);
        $this->assertStringStartsWith('UPDATE', $queries[1]);
    }

    private function enable(NotificationType $notificationType): UserCallbackData
    {
        return UserCallbackData::create(UserCallbackAction::EnableNotification)->withNotificationType($notificationType);
    }

    private function processThroughHandler(UserCallbackData $callbackData): void
    {
        $update = $this->update($callbackData);

        new UserCallbackQueryHandler()->createProcessor($this->telegramSender, $update)->process($update);
    }

    private function update(UserCallbackData $callbackData): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload(
                data: $callbackData->toJson(),
                fromId: self::SENDER_ID,
                chatId: self::SENDER_ID,
            ),
        );
    }
}
