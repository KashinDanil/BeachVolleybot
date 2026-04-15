<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\AdminProcessors;

use BeachVolleybot\Log\LogFileRepository;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\LogClearCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\LogFileActionsCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\LogGetCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\LogsListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\LogTailCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class LogProcessorsTest extends ProcessorTestCase
{
    private string $testLogFile;

    public function testLogsListEditsMessage(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::Logs);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new LogsListCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    public function testLogFileActionsEditsMessage(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::LogFile)->withFilename('test.log');
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new LogFileActionsCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    // --- LogsListProcessor ---

    public function testLogFileActionsRejectsInvalidFilename(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::LogFile)->withFilename('../etc/passwd');
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new LogFileActionsCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageNotEdited();
    }

    // --- LogFileActionsProcessor ---

    public function testLogGetSendsDocument(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::LogGet)->withFilename('test.log');
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new LogGetCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertDocumentSent();
    }

    public function testLogGetRejectsInvalidFilename(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::LogGet)->withFilename('../../etc/passwd');
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new LogGetCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $calls = array_filter($this->bot->calls, fn($c) => 'sendDocument' === $c['method']);
        $this->assertEmpty($calls);
    }

    // --- LogGetProcessor ---

    public function testLogTailEditsMessage(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::LogTail)->withFilename('test.log');
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new LogTailCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    public function testLogClearEmptiesFileAndEditsMessage(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::LogClear)->withFilename('test.log');
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new LogClearCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertSame('', file_get_contents($this->testLogFile));
        $this->assertMessageEdited();
    }

    // --- LogTailProcessor ---

    public function testLogTailRejectsInvalidFilename(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::LogTail)->withFilename('../etc/passwd');
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new LogTailCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageNotEdited();
        $this->assertAnsweredWith(LogFileRepository::INVALID_FILENAME);
    }

    // --- LogClearProcessor ---

    public function testLogClearRejectsInvalidFilename(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::LogClear)->withFilename('../etc/passwd');
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new LogClearCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageNotEdited();
        $this->assertAnsweredWith(LogFileRepository::INVALID_FILENAME);
    }

    // --- LogTailProcessor: invalid filename ---

    public function testLogGetAnswersFileNotFoundForMissingFile(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::LogGet)->withFilename('nonexistent.log');
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new LogGetCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $documentCalls = array_filter($this->bot->calls, fn($c) => 'sendDocument' === $c['method']);
        $this->assertEmpty($documentCalls);
        $this->assertAnsweredWith('File not found');
    }

    // --- LogClearProcessor: invalid filename ---

    public function testLogClearAnswersWithCleared(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::LogClear)->withFilename('test.log');
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new LogClearCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertAnsweredWith('Cleared');
    }

    // --- LogGetProcessor: file not found ---

    public function testLogTailRejectsInvalidFilenameWithNullFilename(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::LogTail);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new LogTailCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageNotEdited();
        $this->assertAnsweredWith(LogFileRepository::INVALID_FILENAME);
    }

    // --- LogClearProcessor: verifies callback answer ---

    public function testIsValidFilenameRejectsUnsafeNames(): void
    {
        $unsafe = ['../etc/passwd', '../../secret', '/etc/passwd', 'file with spaces.log', 'file;rm -rf.log'];

        foreach ($unsafe as $filename) {
            $this->assertFalse(LogFileRepository::isValidFilename($filename), "Should reject: $filename");
        }
    }

    // --- LogTailProcessor: valid filename ---

    public function testIsValidFilenameAcceptsSafeNames(): void
    {
        $safe = ['app.log', 'verbose.log', 'user_actions.log', 'web.log', 'test-file.log'];

        foreach ($safe as $filename) {
            $this->assertTrue(LogFileRepository::isValidFilename($filename), "Should accept: $filename");
        }
    }

    // --- filename validation ---

    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogFile = BASE_LOG_DIR . '/test.log';
        file_put_contents($this->testLogFile, "line1\nline2\nline3\nline4\nline5\nline6\nline7\nline8\nline9\nline10\nline11\n");
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
    }
}
