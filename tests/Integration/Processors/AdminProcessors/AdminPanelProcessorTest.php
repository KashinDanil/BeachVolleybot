<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\AdminProcessors;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\AdminPanelCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminPanelCommandProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\Role;

final class AdminPanelProcessorTest extends ProcessorTestCase
{
    use CreatesUserRecords;

    public function testAdminCommandSendsMessage(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/admin'));

        new AdminPanelCommandProcessor($this->telegramSender, $this->ensureSender($update))->process($update);

        $this->assertMessageSent();
    }

    public function testMainCallbackEditsMessage(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::AdminPanel);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminPanelCallbackProcessor($this->telegramSender, $callbackData, $this->ensureSender($update))->process($update);

        $this->assertMessageEdited();
    }

    public function testCommandShowsLogsButtonForRoot(): void
    {
        $this->seedRoot();
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/admin'));

        new AdminPanelCommandProcessor($this->telegramSender, $this->ensureSender($update))->process($update);

        $this->assertContains('Logs', $this->lastKeyboardLabels('sendMessage'));
    }

    public function testCommandHidesLogsButtonForAdmin(): void
    {
        $this->seedAdmin();
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/admin'));

        new AdminPanelCommandProcessor($this->telegramSender, $this->ensureSender($update))->process($update);

        $this->assertNotContains('Logs', $this->lastKeyboardLabels('sendMessage'));
    }

    public function testCommandBuildsTheMenuForTheGivenSenderWithoutAQuery(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/admin'));
        $processor = new AdminPanelCommandProcessor($this->telegramSender, $this->userRecord(role: Role::Root));

        $queries = $this->queriesDuring(fn() => $processor->process($update));

        $this->assertSame([], $queries);
        $this->assertContains('Logs', $this->lastKeyboardLabels('sendMessage'));
    }

    public function testCallbackBuildsTheMenuForTheGivenSenderWithoutAQuery(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::AdminPanel);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));
        $processor = new AdminPanelCallbackProcessor(
            $this->telegramSender,
            $callbackData,
            $this->userRecord(role: Role::Admin),
        );

        $queries = $this->queriesDuring(fn() => $processor->process($update));

        $this->assertSame([], $queries);
        $this->assertNotContains('Logs', $this->lastKeyboardLabels('editMessageText'));
        $this->assertContains('Games', $this->lastKeyboardLabels('editMessageText'));
    }
}
