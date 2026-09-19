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

    public function testHelpPrivateMatchesItsOwnBareCommand(): void
    {
        $this->assertTrue(Command::HelpPrivate->matches('/help_private'));
    }

    public function testHelpPrivateDoesNotMatchHelp(): void
    {
        $this->assertFalse(Command::HelpPrivate->matches('/help'));
    }

    public function testNewGamePrivateMatchesItsOwnBareCommand(): void
    {
        $this->assertTrue(Command::NewGamePrivate->matches('/new_game_private'));
    }

    public function testNewGamePrivateDoesNotMatchNewGame(): void
    {
        $this->assertFalse(Command::NewGamePrivate->matches('/new_game'));
    }

    public function testForChatReturnsBareCommandForPrivateChat(): void
    {
        $this->assertSame('/new_game', Command::NewGame->forChat(false));
    }

    public function testForChatReturnsMentionForGroupChat(): void
    {
        $this->assertSame('/new_game@test_bot', Command::NewGame->forChat(true));
    }
}
