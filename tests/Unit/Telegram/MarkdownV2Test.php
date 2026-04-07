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
}