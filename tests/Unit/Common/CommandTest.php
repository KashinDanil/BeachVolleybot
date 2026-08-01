<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Common\Command;
use PHPUnit\Framework\TestCase;

final class CommandTest extends TestCase
{
    public function testMatchesBareCommand(): void
    {
        $this->assertTrue(Command::NewGame->matches('/new_game'));
    }

    public function testMatchesCommandMentioningTheBot(): void
    {
        $this->assertTrue(Command::NewGame->matches('/new_game@test_bot'));
    }

    public function testDoesNotMatchUnrelatedText(): void
    {
        $this->assertFalse(Command::NewGame->matches('/help'));
    }

    public function testDoesNotMatchNullText(): void
    {
        $this->assertFalse(Command::NewGame->matches(null));
    }

    public function testMentionAppendsTheBotUsername(): void
    {
        $this->assertSame('/new_game@test_bot', Command::NewGame->mention());
    }
}
