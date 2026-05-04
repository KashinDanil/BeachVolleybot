<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Telegram;

use BeachVolleybot\Telegram\InlineMessageRefresher;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class InlineMessageRefresherTest extends ProcessorTestCase
{
    public function testEditsInlineMessage(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_42', title: 'Game 18:00');

        new InlineMessageRefresher($this->telegramSender)->refresh($gameId);

        $this->assertMessageEdited();
    }

    public function testEditsEveryAttachedInlineMessage(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_a', title: 'Game 18:00');
        $this->attachInlineMessage($gameId, 'msg_b');
        $this->attachInlineMessage($gameId, 'msg_c');

        new InlineMessageRefresher($this->telegramSender)->refresh($gameId);

        $editedIds = array_map(
            fn (array $call) => $call['args'][6],
            array_filter($this->bot->calls, fn (array $call) => 'editMessageText' === $call['method']),
        );
        $this->assertSame(['msg_a', 'msg_b', 'msg_c'], array_values($editedIds));
    }
}
