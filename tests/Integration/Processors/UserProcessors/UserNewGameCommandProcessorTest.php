<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UserProcessors;

use BeachVolleybot\Processors\UserProcessors\UserNewGameCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class UserNewGameCommandProcessorTest extends ProcessorTestCase
{
    public function testSendsThePickerAndDeletesTheCommand(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/new_game', fromId: 200));

        new UserNewGameCommandProcessor($this->telegramSender)->process($update);

        $this->assertSame(1, $this->sendMessageCount());
        $this->assertSame([200, 109], $this->deletedMessage());
    }

    public function testDoesNotDeleteWhenThePickerFailedToSend(): void
    {
        $this->bot->failSend = true;
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/new_game', fromId: 200));

        new UserNewGameCommandProcessor($this->telegramSender)->process($update);

        $this->assertNull($this->deletedMessage());
    }

    private function sendMessageCount(): int
    {
        return count(array_filter($this->bot->calls, static fn(array $call): bool => 'sendMessage' === $call['method']));
    }

    /** @return ?array{0: int, 1: int} */
    private function deletedMessage(): ?array
    {
        foreach ($this->bot->calls as $call) {
            if ('deleteMessage' === $call['method']) {
                return [$call['args'][0], $call['args'][1]];
            }
        }

        return null;
    }
}
