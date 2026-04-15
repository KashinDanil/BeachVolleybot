<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\AdminProcessors;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class SettingsMenuProcessorTest extends ProcessorTestCase
{
    public function testSettingsCommandSendsMessage(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/settings'));

        new SettingsMenuCallbackProcessor($this->telegramSender, AdminCallbackData::create(AdminCallbackAction::Settings))->process($update);

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
}
