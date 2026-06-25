<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Processors\UpdateProcessors\AdminPresenceCheckProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class AdminPresenceCheckProcessorTest extends ProcessorTestCase
{
    private string $appLogPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appLogPath = BASE_LOG_DIR . '/app.log';

        if (file_exists($this->appLogPath)) {
            unlink($this->appLogPath);
        }
    }

    public function testLogsWhenNoConfiguredAdminIsPresent(): void
    {
        $this->bot->chatMemberStatuses = [ADMINS_TELEGRAM_USER_IDS[0] => 'left'];
        $update = TelegramUpdate::fromArray($this->viaBotKeyboardMessagePayload(chatId: -200));

        new AdminPresenceCheckProcessor($this->telegramSender)->process($update);

        $this->assertFileExists($this->appLogPath);
        $this->assertStringContainsString(
            "No configured admin found in chat: chatId=-200;chatType=group;chatTitle='';messageId=60",
            file_get_contents($this->appLogPath),
        );
        $this->assertSame('editMessageText', $this->bot->calls[1]['method']);
        $this->assertSame(-200, $this->bot->calls[1]['args'][0]);
        $this->assertSame(60, $this->bot->calls[1]['args'][1]);
        $this->assertSame(
            'This game has turned into a pumpkin 🎃 because this is an *unauthorized use* of the bot',
            $this->bot->calls[1]['args'][2],
        );
        $this->assertStringNotContainsString('admin', $this->bot->calls[1]['args'][2]);
        $this->assertStringNotContainsString('gently', $this->bot->calls[1]['args'][2]);
        $this->assertStringNotContainsString('fairy', $this->bot->calls[1]['args'][2]);
        $this->assertSame('MarkdownV2', $this->bot->calls[1]['args'][3]);
        $this->assertTrue($this->bot->calls[1]['args'][4]);
        $this->assertNull($this->bot->calls[1]['args'][5]);
    }

    public function testDoesNotLogWhenConfiguredAdminIsPresent(): void
    {
        $this->bot->chatMemberStatuses = [ADMINS_TELEGRAM_USER_IDS[0] => 'member'];
        $update = TelegramUpdate::fromArray($this->viaBotKeyboardMessagePayload(chatId: -200));

        new AdminPresenceCheckProcessor($this->telegramSender)->process($update);

        $this->assertFileDoesNotExist($this->appLogPath);
        $this->assertSame('getChatMember', $this->bot->calls[0]['method']);
        $this->assertCount(1, $this->bot->calls);
    }

    public function testSkipsPrivateChats(): void
    {
        $payload = $this->viaBotKeyboardMessagePayload(chatId: 200);
        $payload['message']['chat']['type'] = 'private';
        $update = TelegramUpdate::fromArray($payload);

        new AdminPresenceCheckProcessor($this->telegramSender)->process($update);

        $this->assertSame([], $this->bot->calls);
        $this->assertFileDoesNotExist($this->appLogPath);
    }
}
