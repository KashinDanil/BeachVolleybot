<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Common;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Errors\ErrorInterface;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    private string $logsDir;

    protected function setUp(): void
    {
        $this->logsDir = BASE_LOG_DIR;
        mkdir($this->logsDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logsDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        
        @rmdir($this->logsDir);
    }

    public function testLogAppCreatesAppLogFile(): void
    {
        Logger::logApp('test');

        $this->assertFileExists($this->logsDir . '/app.log');
    }

    public function testLogWebCreatesWebLogFile(): void
    {
        Logger::logWeb('test');

        $this->assertFileExists($this->logsDir . '/web.log');
    }

    public function testLogAppWritesMessageToFile(): void
    {
        Logger::logApp('hello world');

        $this->assertStringContainsString('hello world', file_get_contents($this->logsDir . '/app.log'));
    }

    public function testLogWebWritesMessageToFile(): void
    {
        Logger::logWeb('hello world');

        $this->assertStringContainsString('hello world', file_get_contents($this->logsDir . '/web.log'));
    }

    public function testLogMessageContainsIso8601Timestamp(): void
    {
        Logger::logApp('timestamped');

        $content = file_get_contents($this->logsDir . '/app.log');
        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[^]]+]/', $content);
    }

    public function testLogAppAppendsOnMultipleCalls(): void
    {
        Logger::logApp('first');
        Logger::logApp('second');

        $content = file_get_contents($this->logsDir . '/app.log');
        $this->assertStringContainsString('first', $content);
        $this->assertStringContainsString('second', $content);
    }

    public function testLogUnauthorizedAccessAttemptWritesToWebLog(): void
    {
        $error = new class implements ErrorInterface {
            public function getMessage(): string { return 'Invalid token'; }
            public function getData(): array { return ['foo' => 'bar']; }
        };

        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
        $_SERVER['REQUEST_URI']     = '/api/test';
        $_SERVER['REQUEST_METHOD']  = 'POST';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';

        Logger::logUnauthorizedAccessAttempt($error);

        $content = file_get_contents($this->logsDir . '/web.log');
        $this->assertStringContainsString('Invalid token', $content);
        $this->assertStringContainsString('127.0.0.1', $content);
        $this->assertStringContainsString('/api/test', $content);
        $this->assertStringContainsString('POST', $content);
        $this->assertStringContainsString('TestAgent/1.0', $content);
    }
}