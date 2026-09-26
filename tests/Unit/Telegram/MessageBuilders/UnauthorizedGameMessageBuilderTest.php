<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\UnauthorizedGameMessageBuilder;
use PHPUnit\Framework\TestCase;

final class UnauthorizedGameMessageBuilderTest extends TestCase
{
    public function testBuildsUnauthorizedGameMessageWithoutKeyboard(): void
    {
        $message = new UnauthorizedGameMessageBuilder()->build();

        $this->assertSame(
            'This game has turned into a pumpkin 🎃 because this is an *unauthorized use* of the bot',
            $message->getText()->getMessageText(),
        );
        $this->assertSame('MarkdownV2', $message->getText()->getParseMode());
        $this->assertTrue($message->getText()->isDisableWebPagePreview());
        $this->assertNull($message->getKeyboard());
    }
}
