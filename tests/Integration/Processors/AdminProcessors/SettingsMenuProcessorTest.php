<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\AdminProcessors;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCommandProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class SettingsMenuProcessorTest extends ProcessorTestCase
{
    public function testSettingsCommandSendsMessage(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/settings'));

        new SettingsMenuCommandProcessor($this->telegramSender)->process($update);

        $this->assertMessageSent();
    }

    public function testMainCallbackEditsMessage(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::Settings);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new SettingsMenuCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    public function testCommandShowsLogsButtonForRoot(): void
    {
        $this->seedRoot();
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/settings'));

        new SettingsMenuCommandProcessor($this->telegramSender)->process($update);

        $this->assertContains('Logs', $this->sentKeyboardLabels());
    }

    public function testCommandHidesLogsButtonForAdmin(): void
    {
        $this->seedAdmin();
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/settings'));

        new SettingsMenuCommandProcessor($this->telegramSender)->process($update);

        $this->assertNotContains('Logs', $this->sentKeyboardLabels());
    }

    /** @return list<string> */
    private function sentKeyboardLabels(): array
    {
        $sendCalls = array_values(array_filter($this->bot->calls, fn($c) => 'sendMessage' === $c['method']));
        $keyboard = end($sendCalls)['args'][5];
        $rows = json_decode($keyboard->toJson(), true)['inline_keyboard'];

        return array_column(array_merge(...$rows), 'text');
    }
}
