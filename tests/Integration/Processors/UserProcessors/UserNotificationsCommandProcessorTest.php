<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UserProcessors;

use BeachVolleybot\Common\Command;
use BeachVolleybot\Processors\UserProcessors\UserNotificationsCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;

final class UserNotificationsCommandProcessorTest extends ProcessorTestCase
{
    use CreatesUserRecords;

    private const int SENDER_ID = 555;

    public function testSendsTheNotificationsList(): void
    {
        $this->processCommand();

        $sendCall = $this->lastSendMessageCall();
        $this->assertNotNull($sendCall, 'Expected sendMessage to be called');
        $this->assertSame(self::SENDER_ID, $sendCall['args'][0]);
        $this->assertStringContainsString('Choose a notification to set it up', $sendCall['args'][1]);
        $this->assertCount(count(NotificationType::cases()), $this->lastKeyboard('sendMessage'));
    }

    private function processCommand(): void
    {
        $update = TelegramUpdate::fromArray(
            $this->privateMessagePayload(Command::Notifications->value, fromId: self::SENDER_ID),
        );

        new UserNotificationsCommandProcessor($this->telegramSender, $this->ensureSender($update))->process($update);
    }

    public function testListsTheGivenSendersNotificationsWithoutAQuery(): void
    {
        $update = TelegramUpdate::fromArray(
            $this->privateMessagePayload(Command::Notifications->value, fromId: self::SENDER_ID),
        );
        $sender = $this->userRecord(
            telegramUserId: self::SENDER_ID,
            notifications: new NotificationSettings()->enable(NotificationType::PromotedIntoGame),
        );
        $processor = new UserNotificationsCommandProcessor($this->telegramSender, $sender);

        $queries = $this->queriesDuring(fn() => $processor->process($update));

        $this->assertSame([], $queries);
        $this->assertSame('success', $this->lastKeyboard('sendMessage')[2][0]['style'] ?? null);
    }

    private function lastSendMessageCall(): ?array
    {
        $calls = array_filter($this->bot->calls, fn($call) => 'sendMessage' === $call['method']);

        if (empty($calls)) {
            return null;
        }

        return end($calls);
    }

    public function testDeletesTheNotificationsCommandMessage(): void
    {
        $this->processCommand();

        $deleteCalls = array_filter($this->bot->calls, fn($call) => 'deleteMessage' === $call['method']);
        $this->assertCount(1, $deleteCalls);

        $deleteCall = end($deleteCalls);
        $this->assertSame(self::SENDER_ID, $deleteCall['args'][0]);
        $this->assertSame(109, $deleteCall['args'][1]);
    }

    public function testUnsetNotificationsListEveryTypeAsOff(): void
    {
        $this->createUser(telegramUserId: self::SENDER_ID);

        $this->processCommand();

        foreach ($this->lastKeyboard('sendMessage') as $row) {
            $this->assertArrayNotHasKey('style', $row[0]);
        }
    }

    public function testMarksEnabledNotificationsInTheList(): void
    {
        $this->createUser(telegramUserId: self::SENDER_ID);
        $userManager = new UserManager();
        $userManager->enableNotification(
            $userManager->findUserRecordById(self::SENDER_ID),
            NotificationType::PromotedIntoGame,
        );

        $this->processCommand();

        $keyboard = $this->lastKeyboard('sendMessage');
        $this->assertSame('success', $keyboard[2][0]['style'] ?? null);
        $this->assertArrayNotHasKey('style', $keyboard[0][0]);
    }
}
