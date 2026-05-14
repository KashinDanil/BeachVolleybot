<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UserProcessors;

use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserStartCommandHandler;
use BeachVolleybot\Processors\UserProcessors\UserStartCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class UserStartCommandProcessorTest extends ProcessorTestCase
{
    private const int SENDER_ID = 555;

    public function testSendsTheWelcomeMessage(): void
    {
        $this->processCommand();

        $sendCalls = array_values(array_filter($this->bot->calls, fn($call) => 'sendMessage' === $call['method']));
        $this->assertCount(1, $sendCalls, 'Expected sendMessage to be called once');

        $args = $sendCalls[0]['args'];
        $this->assertSame(self::SENDER_ID, $args[0]);

        $text = (string)$args[1];
        $this->assertStringContainsString('@' . BOT_USERNAME, $text);
        $this->assertStringContainsString('17:30', $text);

        $this->assertSame('MarkdownV2', $args[2]);
    }

    public function testDeletesTheStartCommandMessage(): void
    {
        $this->processCommand();

        $deleteCalls = array_filter($this->bot->calls, fn($call) => 'deleteMessage' === $call['method']);
        $this->assertCount(1, $deleteCalls);

        $deleteCall = end($deleteCalls);
        $this->assertSame(self::SENDER_ID, $deleteCall['args'][0]);
        $this->assertSame(109, $deleteCall['args'][1]);
    }

    private function processCommand(): void
    {
        $update = TelegramUpdate::fromArray(
            $this->privateMessagePayload(UserStartCommandHandler::COMMAND, fromId: self::SENDER_ID),
        );

        new UserStartCommandProcessor($this->telegramSender)->process($update);
    }
}
