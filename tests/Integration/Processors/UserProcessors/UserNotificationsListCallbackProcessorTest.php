<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UserProcessors;

use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Processors\UserProcessors\UserNotificationsListCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;

final class UserNotificationsListCallbackProcessorTest extends ProcessorTestCase
{
    use CreatesUserRecords;

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

        new UserNotificationsListCallbackProcessor($this->telegramSender, $this->ensureSender($update))->process($update);
    }

    public function testListsTheGivenSendersNotificationsWithoutAQuery(): void
    {
        $callbackData = UserCallbackData::create(UserCallbackAction::NotificationsList);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload(data: $callbackData->toJson(), fromId: self::SENDER_ID, chatId: self::SENDER_ID),
        );
        $sender = $this->userRecord(
            telegramUserId: self::SENDER_ID,
            notifications: new NotificationSettings()->enable(NotificationType::BumpedFromGame),
        );
        $processor = new UserNotificationsListCallbackProcessor($this->telegramSender, $sender);

        $queries = $this->queriesDuring(fn() => $processor->process($update));

        $this->assertSame([], $queries);
        $keyboard = $this->lastKeyboard('editMessageText');
        $this->assertSame('success', $keyboard[NotificationType::BumpedFromGame->value - 1][0]['style'] ?? null);
    }

    public function testAnswersTheCallbackSilently(): void
    {
        $this->processCallback();

        $this->assertAnsweredWith('');
    }

    public function testUnsetNotificationsListEveryTypeAsOff(): void
    {
        $this->createUser(telegramUserId: self::SENDER_ID);

        $this->processCallback();

        foreach ($this->lastKeyboard('editMessageText') as $row) {
            $this->assertArrayNotHasKey('style', $row[0]);
        }
    }

    public function testMarksEnabledNotificationsInTheList(): void
    {
        $this->createUser(telegramUserId: self::SENDER_ID);
        $this->db->update(
            'users',
            ['notifications' => new NotificationSettings()->enable(NotificationType::BumpedFromGame)->toInt()],
            ['telegram_user_id' => self::SENDER_ID],
        );

        $this->processCallback();

        $keyboard = $this->lastKeyboard('editMessageText');
        $this->assertSame('success', $keyboard[NotificationType::BumpedFromGame->value - 1][0]['style'] ?? null);
        $this->assertArrayNotHasKey('style', $keyboard[NotificationType::PromotedIntoGame->value - 1][0]);
    }
}
