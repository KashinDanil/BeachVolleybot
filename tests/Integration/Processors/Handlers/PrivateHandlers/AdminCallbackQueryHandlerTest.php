<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\AdminGamesListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\LogsListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\RestrictedActionCallbackProcessor;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\AdminCallbackQueryHandler;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class AdminCallbackQueryHandlerTest extends ProcessorTestCase
{
    private AdminCallbackQueryHandler $handler;

    public function testAdminMatchesRootOnlyLogsCallback(): void
    {
        $this->seedAdmin();
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload('{"aa":"lgs"}'));

        $this->assertTrue($this->handler->matches($update));
    }

    public function testAdminGetsRestrictedProcessorForRootOnlyLogsCallback(): void
    {
        $this->seedAdmin();
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload('{"aa":"lgs"}'));

        $processor = $this->handler->createProcessor($this->telegramSender, $update);

        $this->assertInstanceOf(RestrictedActionCallbackProcessor::class, $processor);
    }

    public function testRootGetsRealProcessorForLogsCallback(): void
    {
        $this->seedRoot();
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload('{"aa":"lgs"}'));

        $processor = $this->handler->createProcessor($this->telegramSender, $update);

        $this->assertInstanceOf(LogsListCallbackProcessor::class, $processor);
    }

    public function testAdminGetsRealProcessorForGamesCallback(): void
    {
        $this->seedAdmin();
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload('{"aa":"gl"}'));

        $processor = $this->handler->createProcessor($this->telegramSender, $update);

        $this->assertInstanceOf(AdminGamesListCallbackProcessor::class, $processor);
    }

    public function testRestrictedProcessorMovesMessageBackToSettingsAndAnswers(): void
    {
        $this->seedAdmin();
        $callbackData = AdminCallbackData::create(AdminCallbackAction::Logs);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RestrictedActionCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $edits = array_values(array_filter($this->bot->calls, fn($call) => 'editMessageText' === $call['method']));
        $this->assertNotEmpty($edits, 'Expected the message to be moved back to the settings menu');
        $this->assertStringContainsString('Settings', $edits[0]['args'][2]);

        $answered = array_filter($this->bot->calls, fn($call) => 'answerCallbackQuery' === $call['method']);
        $this->assertNotEmpty($answered, 'Expected the callback query to be answered');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new AdminCallbackQueryHandler();
    }
}
