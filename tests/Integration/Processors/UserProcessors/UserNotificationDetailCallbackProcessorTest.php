<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UserProcessors;

use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Processors\UserProcessors\UserNotificationDetailCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\NotificationType;

final class UserNotificationDetailCallbackProcessorTest extends ProcessorTestCase
{
    private const int SENDER_ID = 555;

    public function testEditsTheMessageToTheNotificationDetail(): void
    {
        $this->processCallback(
            UserCallbackData::create(UserCallbackAction::NotificationDetail)
                ->withNotificationType(NotificationType::GameReachedMinimumPlayers)
        );

        $this->assertMessageEdited();
        $this->assertStringContainsString('✅ Game is on', $this->editedText());
        $this->assertStringContainsString("🔕 You won't get a notification when", $this->editedText());
        $this->assertAnsweredWith('');
    }

    private function processCallback(UserCallbackData $callbackData): void
    {
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload(
                data: $callbackData->toJson(),
                fromId: self::SENDER_ID,
                chatId: self::SENDER_ID,
            ),
        );

        new UserNotificationDetailCallbackProcessor($this->telegramSender, $callbackData)->process($update);
    }

    public function testUnknownNotificationTypeOnlyAnswersTheCallback(): void
    {
        $this->processCallback(UserCallbackData::fromJson('{"ua":"und","n":99}'));

        $this->assertMessageNotEdited();
        $this->assertAnsweredWith('');
    }
}
