<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UserProcessors;

use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Processors\UserProcessors\UserNotificationsListCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class UserNotificationsListCallbackProcessorTest extends ProcessorTestCase
{
    private const int SENDER_ID = 555;

    public function testEditsTheMessageBackToTheList(): void
    {
        $this->processCallback();

        $this->assertMessageEdited();
        $this->assertStringContainsString('Choose a notification to set it up.', $this->editedText());
    }

    private function processCallback(): void
    {
        $callbackData = UserCallbackData::create(UserCallbackAction::NotificationsList);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload(
                data: $callbackData->toJson(),
                fromId: self::SENDER_ID,
                chatId: self::SENDER_ID,
            ),
        );

        new UserNotificationsListCallbackProcessor($this->telegramSender)->process($update);
    }

    public function testAnswersTheCallbackSilently(): void
    {
        $this->processCallback();

        $this->assertAnsweredWith('');
    }
}
