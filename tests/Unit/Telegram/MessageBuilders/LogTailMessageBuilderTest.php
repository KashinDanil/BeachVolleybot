<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Log\LogFileEntry;
use BeachVolleybot\Telegram\MessageBuilders\LogTailMessageBuilder;
use PHPUnit\Framework\TestCase;

final class LogTailMessageBuilderTest extends TestCase
{
    private LogTailMessageBuilder $builder;

    public function testBuildLogTailContainsHeader(): void
    {
        $entry = new LogFileEntry('app.log', 1024);

        $message = $this->builder->buildLogTail($entry, "line1\nline2");

        $this->assertStringContainsString('Logs', $message->getText()->getMessageText());
    }

    public function testBuildLogTailShowsFilenameAndSize(): void
    {
        $entry = new LogFileEntry('app.log', 1024);

        $message = $this->builder->buildLogTail($entry, "line1\nline2");

        $this->assertStringContainsString('app\\.log', $message->getText()->getMessageText());
    }

    public function testBuildLogTailShowsContentInCodeBlock(): void
    {
        $entry = new LogFileEntry('app.log', 100);
        $content = "error: something\nwarn: other";

        $message = $this->builder->buildLogTail($entry, $content);
        $text = $message->getText()->getMessageText();

        $this->assertStringContainsString('```', $text);
        $this->assertStringContainsString('error: something', $text);
    }

    public function testBuildLogTailShowsEmptyMessageWhenContentIsNull(): void
    {
        $entry = new LogFileEntry('app.log', 0);

        $message = $this->builder->buildLogTail($entry, null);

        $this->assertStringContainsString('File is empty', $message->getText()->getMessageText());
    }

    public function testBuildLogTailHasBackButton(): void
    {
        $entry = new LogFileEntry('app.log', 100);

        $message = $this->builder->buildLogTail($entry, 'content');
        $keyboard = $this->extractKeyboard($message);

        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    private function extractKeyboard($message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }

    protected function setUp(): void
    {
        $this->builder = new LogTailMessageBuilder();
    }
}
