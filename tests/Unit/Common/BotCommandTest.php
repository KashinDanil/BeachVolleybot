<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Common\BotCommand;
use PHPUnit\Framework\TestCase;

final class BotCommandTest extends TestCase
{
    public function testMatchesBareCommand(): void
    {
        $this->assertTrue(BotCommand::matches('/new_game', '/new_game'));
    }

    public function testMatchesCommandMentioningTheBot(): void
    {
        $this->assertTrue(BotCommand::matches('/new_game', '/new_game@test_bot'));
    }

    public function testDoesNotMatchUnrelatedText(): void
    {
        $this->assertFalse(BotCommand::matches('/new_game', '/help'));
    }

    public function testDoesNotMatchNullText(): void
    {
        $this->assertFalse(BotCommand::matches('/new_game', null));
    }

    public function testMentionAppendsTheBotUsername(): void
    {
        $this->assertSame('/new_game@test_bot', BotCommand::mention('/new_game'));
    }
}
