<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\UnauthorizedGroupMessageBuilder;
use PHPUnit\Framework\TestCase;

final class UnauthorizedGroupMessageBuilderTest extends TestCase
{
    public function testBuildsTheNoticeWithoutKeyboard(): void
    {
        $message = new UnauthorizedGroupMessageBuilder()->build();

        $this->assertSame(
            '🚫 The bot is not authorized in this group\.',
            $message->getText()->getMessageText(),
        );
        $this->assertSame('MarkdownV2', $message->getText()->getParseMode());
        $this->assertNull($message->getKeyboard());
    }
}
