<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UserProcessors;

use BeachVolleybot\Common\Command;
use BeachVolleybot\Processors\UserProcessors\UserNotificationsCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

final class UserNotificationsCommandProcessorTest extends ProcessorTestCase
{
    private const int SENDER_ID = 555;

    public function testSendsTheNotificationsList(): void
    {
        $this->processCommand();

        $sendCall = $this->lastSendMessageCall();
        $this->assertNotNull($sendCall, 'Expected sendMessage to be called');
        $this->assertSame(self::SENDER_ID, $sendCall['args'][0]);
        $this->assertStringContainsString('Choose a notification to set it up', $sendCall['args'][1]);
        $this->assertCount(count(NotificationType::cases()), $this->extractKeyboard($sendCall));
    }

    private function processCommand(): void
    {
        $update = TelegramUpdate::fromArray(
            $this->privateMessagePayload(Command::Notifications->value, fromId: self::SENDER_ID),
        );

        new UserNotificationsCommandProcessor($this->telegramSender)->process($update);
    }

    private function lastSendMessageCall(): ?array
    {
        $calls = array_filter($this->bot->calls, fn($call) => 'sendMessage' === $call['method']);

        if (empty($calls)) {
            return null;
        }

        return end($calls);
    }

    private function extractKeyboard(?array $sendCall): array
    {
        $this->assertNotNull($sendCall);
        /** @var InlineKeyboardMarkup $keyboard */
        $keyboard = $sendCall['args'][5];

        return json_decode($keyboard->toJson(), true)['inline_keyboard'];
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

    public function testCreatesAMissingUserWithEveryNotificationOff(): void
    {
        $this->processCommand();

        $record = new UserManager()->findUserRecordById(self::SENDER_ID);
        $this->assertNotNull($record);
        $this->assertSame(0, $record->notifications->toInt());
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

        $keyboard = $this->extractKeyboard($this->lastSendMessageCall());
        $this->assertSame('success', $keyboard[2][0]['style'] ?? null);
        $this->assertArrayNotHasKey('style', $keyboard[0][0]);
    }
}
