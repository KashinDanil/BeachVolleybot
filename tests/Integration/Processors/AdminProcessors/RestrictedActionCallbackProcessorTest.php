<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\AdminProcessors;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\RestrictedActionCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\Role;

final class RestrictedActionCallbackProcessorTest extends ProcessorTestCase
{
    use CreatesUserRecords;

    private const string RESTRICTED = 'Access restricted';

    public function testPlayerGetsTheRestrictedMessageWithoutAQuery(): void
    {
        $queries = $this->queriesDuring(fn() => $this->processAs(Role::Player));

        $this->assertSame([], $queries);
        $this->assertAnsweredWith(self::RESTRICTED);
        $this->assertSame('*' . self::RESTRICTED . '*', $this->editedText());
        $this->assertSame([], $this->lastKeyboard('editMessageText'));
    }

    public function testAdminIsMovedBackToTheSettingsMenuWithoutAQuery(): void
    {
        $queries = $this->queriesDuring(fn() => $this->processAs(Role::Admin));

        $this->assertSame([], $queries);
        $this->assertAnsweredWith(self::RESTRICTED);
        $this->assertStringContainsString('Settings', $this->editedText());
        $this->assertNotContains('Logs', $this->lastKeyboardLabels('editMessageText'));
    }

    private function processAs(Role $role): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::Logs);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RestrictedActionCallbackProcessor($this->telegramSender, $callbackData, $this->userRecord(role: $role))
            ->process($update);
    }
}
