<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Processors\UpdateProcessors\DeletePinNotificationProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class DeletePinNotificationProcessorTest extends ProcessorTestCase
{
    public function testDeletesPinNotificationFromThisBot(): void
    {
        $update = TelegramUpdate::fromArray($this->pinNotificationPayload(
            chatId: -1003759398496,
            messageId: 313,
            pinnedMessageId: 312,
        ));

        new DeletePinNotificationProcessor($this->telegramSender)->process($update);

        $deleteCalls = array_values(array_filter(
            $this->bot->calls,
            static fn(array $call): bool => 'deleteMessage' === $call['method'],
        ));

        $this->assertCount(1, $deleteCalls);
        $this->assertSame(-1003759398496, $deleteCalls[0]['args'][0]);
        $this->assertSame(313, $deleteCalls[0]['args'][1]);
    }
}
