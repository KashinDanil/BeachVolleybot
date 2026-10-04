<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Common\Command;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserNotificationsCommandHandler;
use BeachVolleybot\Processors\UserProcessors\UserNotificationsCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\UserManager;

final class UserNotificationsCommandHandlerTest extends ProcessorTestCase
{
    private const int SENDER_ID = 555;

    private TelegramUpdate $update;

    public function testHandsTheSenderToTheProcessor(): void
    {
        $processor = new UserNotificationsCommandHandler()->createProcessor($this->telegramSender, $this->update);

        $this->assertEquals(
            new UserNotificationsCommandProcessor(
                $this->telegramSender,
                new UserManager()->findUserRecordById(self::SENDER_ID),
            ),
            $processor,
        );
    }

    public function testCreatesAMissingUserWithNotificationsOff(): void
    {
        $this->processThroughHandler();

        $this->assertSame(0, new UserManager()->findUserRecordById(self::SENDER_ID)?->notifications?->toInt());
    }

    public function testOpeningTheListCostsOneQuery(): void
    {
        $this->createUser(telegramUserId: self::SENDER_ID);

        $queries = $this->queriesDuring(fn() => $this->processThroughHandler());

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('INSERT INTO users', $queries[0]);
    }

    private function processThroughHandler(): void
    {
        new UserNotificationsCommandHandler()->createProcessor($this->telegramSender, $this->update)->process($this->update);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->update = TelegramUpdate::fromArray(
            $this->privateMessagePayload(Command::Notifications->value, fromId: self::SENDER_ID),
        );
    }
}
