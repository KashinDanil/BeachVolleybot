<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram;

use BeachVolleybot\Telegram\MarkdownV2;
use PHPUnit\Framework\TestCase;

final class MarkdownV2Test extends TestCase
{
    private MarkdownV2 $formatter;

    protected function setUp(): void
    {
        $this->formatter = new MarkdownV2();
    }

    // --- parseMode ---

    public function testParseModeReturnsMarkdownV2(): void
    {
        $this->assertSame('MarkdownV2', $this->formatter->parseMode());
    }

    // --- escape ---

    public function testEscapesSpecialCharacters(): void
    {
        $this->assertSame('hello\_world', $this->formatter->escape('hello_world'));
    }

    public function testEscapesDot(): void
    {
        $this->assertSame('1\.', $this->formatter->escape('1.'));
    }

    public function testEscapesExclamationMark(): void
    {
        $this->assertSame('See you there\!', $this->formatter->escape('See you there!'));
    }

    public function testEscapesPlus(): void
    {
        $this->assertSame('\+1', $this->formatter->escape('+1'));
    }

    public function testEscapesDash(): void
    {
        $this->assertSame('4\-7', $this->formatter->escape('4-7'));
    }

    public function testEscapesBackslash(): void
    {
        $this->assertSame('a\\\\b', $this->formatter->escape('a\\b'));
    }

    public function testEscapesMultipleSpecialCharacters(): void
    {
        $this->assertSame('Game \#1 \(test\)', $this->formatter->escape('Game #1 (test)'));
    }

    public function testPlainTextUnchanged(): void
    {
        $this->assertSame('Beach Game 18:00', $this->formatter->escape('Beach Game 18:00'));
    }

    // --- bold ---

    public function testBold(): void
    {
        $this->assertSame('*hello*', $this->formatter->bold('hello'));
    }

    public function testBoldEscapesContent(): void
    {
        $this->assertSame('*hello\_world*', $this->formatter->bold('hello_world'));
    }

    // --- italic ---

    public function testItalic(): void
    {
        $this->assertSame('_hello_', $this->formatter->italic('hello'));
    }

    public function testItalicEscapesContent(): void
    {
        $this->assertSame('_hello\*world_', $this->formatter->italic('hello*world'));
    }

    // --- code ---

    public function testCode(): void
    {
        $this->assertSame('`hello`', $this->formatter->code('hello'));
    }

    public function testCodeEscapesBacktick(): void
    {
        $this->assertSame('`a\`b`', $this->formatter->code('a`b'));
    }

    public function testCodeEscapesBackslash(): void
    {
        $this->assertSame('`a\\\\b`', $this->formatter->code('a\\b'));
    }

    public function testCodeDoesNotEscapeOtherSpecialCharacters(): void
    {
        $this->assertSame('`hello_world`', $this->formatter->code('hello_world'));
    }

    // --- blockquote ---

    public function testBlockquote(): void
    {
        $this->assertSame('>hello', $this->formatter->blockquote('hello'));
    }

    public function testBlockquoteMultiline(): void
    {
        $this->assertSame(">line1\n>line2", $this->formatter->blockquote("line1\nline2"));
    }

    public function testBlockquoteEscapesContent(): void
    {
        $this->assertSame('>hello\!', $this->formatter->blockquote('hello!'));
    }

    // --- link ---

    public function testLinkBasic(): void
    {
        $this->assertSame(
            '[Alice](https://t.me/alice)',
            $this->formatter->link('Alice', 'https://t.me/alice'),
        );
    }

    public function testLinkEscapesTextSpecialCharacters(): void
    {
        $this->assertSame(
            '[Alice\_B](https://t.me/alice)',
            $this->formatter->link('Alice_B', 'https://t.me/alice'),
        );
    }

    public function testLinkEscapesClosingParenInUrl(): void
    {
        $this->assertSame(
            '[Link](https://example.com/path\)end)',
            $this->formatter->link('Link', 'https://example.com/path)end'),
        );
    }

    public function testLinkDoesNotOverEscapeUrl(): void
    {
        $this->assertSame(
            '[📍 Location](https://maps.google.com/?q=41.39,2.20)',
            $this->formatter->link('📍 Location', 'https://maps.google.com/?q=41.39,2.20'),
        );
    }

    // --- customEmoji ---

    public function testCustomEmoji(): void
    {
        $this->assertSame(
            '![🔢](tg://emoji?id=5366536240210394939)',
            $this->formatter->customEmoji('🔢', '5366536240210394939'),
        );
    }

    public function testCustomEmojiEscapesPlaceholder(): void
    {
        $this->assertSame(
            '![test\_emoji](tg://emoji?id=123)',
            $this->formatter->customEmoji('test_emoji', '123'),
        );
    }
}
