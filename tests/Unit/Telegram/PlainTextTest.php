<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram;

use BeachVolleybot\Telegram\PlainText;
use BeachVolleybot\Telegram\Style;
use PHPUnit\Framework\TestCase;

final class PlainTextTest extends TestCase
{
    private PlainText $formatter;

    protected function setUp(): void
    {
        $this->formatter = new PlainText();
    }

    public function testEscapeLeavesMarkdownSpecialsUntouched(): void
    {
        $this->assertSame('Saturday, 31.12 (Bogatell)', $this->formatter->escape('Saturday, 31.12 (Bogatell)'));
    }

    public function testStylingMethodsAddNoMarkup(): void
    {
        $this->assertSame('New game', $this->formatter->bold('New game'));
        $this->assertSame('18:30', $this->formatter->italic('18:30'));
        $this->assertSame('RSVP', $this->formatter->style('RSVP', Style::Bold, Style::Underline));
    }

    public function testNewLineIsALineFeed(): void
    {
        $this->assertSame("\n", $this->formatter->newLine());
    }

    public function testParseModeIsEmpty(): void
    {
        $this->assertSame('', $this->formatter->parseMode());
    }
}
