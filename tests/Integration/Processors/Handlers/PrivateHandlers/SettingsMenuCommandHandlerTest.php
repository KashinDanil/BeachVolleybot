<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\Handlers\PrivateHandlers\SettingsMenuCommandHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\UserManager;

final class SettingsMenuCommandHandlerTest extends ProcessorTestCase
{
    private const int PLAYER_ID = 999;

    private SettingsMenuCommandHandler $handler;

    public function testMatchesTheSettingsCommandFromAnyoneWithoutTouchingTheDatabase(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/settings', fromId: self::PLAYER_ID));

        $this->assertTrue($this->handler->matches($update));
        $this->assertNull(new UserManager()->findUserRecordById(self::PLAYER_ID));
    }

    public function testDoesNotMatchTheSettingsCommandInAGroup(): void
    {
        $payload = $this->privateMessagePayload('/settings', fromId: self::PLAYER_ID);
        $payload['message']['chat'] = ['id' => -100, 'title' => 'Beach', 'type' => 'supergroup'];

        $this->assertFalse($this->handler->matches(TelegramUpdate::fromArray($payload)));
    }

    public function testAdminGetsTheSettingsMenu(): void
    {
        $this->seedAdmin();

        $this->processThroughHandler(self::ADMIN_TELEGRAM_USER_ID);

        $this->assertMessageSent();
        $this->assertMessageDeleted();
    }

    public function testPlayersSettingsCommandIsIgnored(): void
    {
        $this->processThroughHandler(self::PLAYER_ID);

        $this->assertSame([], $this->bot->calls);
        $this->assertNotNull(new UserManager()->findUserRecordById(self::PLAYER_ID));
    }

    private function processThroughHandler(int $fromId): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/settings', fromId: $fromId));

        $this->handler->createProcessor($this->telegramSender, $update)->process($update);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new SettingsMenuCommandHandler();
    }
}
