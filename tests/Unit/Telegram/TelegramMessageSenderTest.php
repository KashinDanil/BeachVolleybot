<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram;

use BeachVolleybot\Telegram\TelegramMessageSender;
use PHPUnit\Framework\TestCase;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\HttpException;

final class TelegramMessageSenderTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = BASE_LOG_DIR . '/app.log';

        if (!is_dir(BASE_LOG_DIR)) {
            mkdir(BASE_LOG_DIR, 0777, true);
        }

        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testAnswerCallbackQueryDoesNotThrowOnHttpException(): void
    {
        $bot = $this->createMock(BotApi::class);
        $bot->method('answerCallbackQuery')
            ->willThrowException(new HttpException('Bad Request: query is too old', 400));

        $sender = new TelegramMessageSender($bot);
        $sender->answerCallbackQuery('expired_id', 'text');

        $this->assertFileExists($this->logFile);
        $this->assertStringContainsString(
            'answerCallbackQuery failed: Bad Request: query is too old',
            file_get_contents($this->logFile),
        );
    }

    public function testAnswerInlineQueryDoesNotThrowOnHttpException(): void
    {
        $bot = $this->createMock(BotApi::class);
        $bot->method('answerInlineQuery')
            ->willThrowException(new HttpException('Bad Request: query is too old', 400));

        $sender = new TelegramMessageSender($bot);
        $sender->answerInlineQuery('expired_id', []);

        $this->assertFileExists($this->logFile);
        $this->assertStringContainsString(
            'answerInlineQuery failed: Bad Request: query is too old',
            file_get_contents($this->logFile),
        );
    }
}
