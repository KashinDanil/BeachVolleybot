<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Telegram;

use BeachVolleybot\Telegram\InlineMessageRefresher;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class InlineMessageRefresherTest extends ProcessorTestCase
{
    public function testEditsInlineMessage(): void
    {
        $this->seedFullGame(inlineMessageId: 'msg_42', title: 'Game 18:00');

        new InlineMessageRefresher($this->telegramSender)->refresh('msg_42');

        $this->assertMessageEdited();
    }
}
