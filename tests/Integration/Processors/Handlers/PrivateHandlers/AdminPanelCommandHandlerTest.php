<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\Handlers\PrivateHandlers\AdminPanelCommandHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\UserManager;

final class AdminPanelCommandHandlerTest extends ProcessorTestCase
{
    private const int PLAYER_ID = 999;

    private AdminPanelCommandHandler $handler;

    public function testMatchesTheAdminCommandFromAnyoneWithoutTouchingTheDatabase(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/admin', fromId: self::PLAYER_ID));

        $this->assertTrue($this->handler->matches($update));
        $this->assertNull(new UserManager()->findUserRecordById(self::PLAYER_ID));
    }

    public function testDoesNotMatchTheAdminCommandInAGroup(): void
    {
        $payload = $this->privateMessagePayload('/admin', fromId: self::PLAYER_ID);
        $payload['message']['chat'] = ['id' => -100, 'title' => 'Beach', 'type' => 'supergroup'];

        $this->assertFalse($this->handler->matches(TelegramUpdate::fromArray($payload)));
    }

    public function testAdminGetsTheAdminPanel(): void
    {
        $this->seedAdmin();

        $this->processThroughHandler(self::ADMIN_TELEGRAM_USER_ID);

        $this->assertMessageSent();
        $this->assertMessageDeleted();
    }

    public function testAdminsAdminCommandCostsOneQuery(): void
    {
        $this->seedAdmin();

        $queries = $this->queriesDuring(fn() => $this->processThroughHandler(self::ADMIN_TELEGRAM_USER_ID));

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('INSERT INTO users', $queries[0]);
    }

    public function testPlayersAdminCommandIsIgnored(): void
    {
        $this->processThroughHandler(self::PLAYER_ID);

        $this->assertSame([], $this->bot->calls);
        $this->assertSame(0, new UserManager()->findUserRecordById(self::PLAYER_ID)?->notifications?->toInt());
    }

    private function processThroughHandler(int $fromId): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/admin', fromId: $fromId));

        $this->handler->createProcessor($this->telegramSender, $update)->process($update);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new AdminPanelCommandHandler();
    }
}
