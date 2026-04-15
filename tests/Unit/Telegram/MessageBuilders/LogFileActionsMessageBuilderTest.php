<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Log\LogFileEntry;
use BeachVolleybot\Telegram\MessageBuilders\LogFileActionsMessageBuilder;
use PHPUnit\Framework\TestCase;

final class LogFileActionsMessageBuilderTest extends TestCase
{
    private LogFileActionsMessageBuilder $builder;

    public function testBuildLogFileActionsContainsHeader(): void
    {
        $entry = new LogFileEntry('app.log', 1024);

        $message = $this->builder->buildLogFileActions($entry);

        $this->assertStringContainsString('Logs', $message->getText()->getMessageText());
    }

    public function testBuildLogFileActionsShowsFilenameAndSize(): void
    {
        $entry = new LogFileEntry('app.log', 2048);

        $message = $this->builder->buildLogFileActions($entry);

        $this->assertStringContainsString('app\\.log', $message->getText()->getMessageText());
        $this->assertStringContainsString('2 KB', $message->getText()->getMessageText());
    }

    public function testBuildLogFileActionsHasGetButton(): void
    {
        $entry = new LogFileEntry('app.log', 100);

        $message = $this->builder->buildLogFileActions($entry);
        $keyboard = $this->extractKeyboard($message);

        $actionRow = $keyboard[0];
        $buttonTexts = array_column($actionRow, 'text');
        $this->assertContains('Get', $buttonTexts);
    }

    private function extractKeyboard($message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }

    public function testBuildLogFileActionsHasTailButton(): void
    {
        $entry = new LogFileEntry('app.log', 100);

        $message = $this->builder->buildLogFileActions($entry);
        $keyboard = $this->extractKeyboard($message);

        $actionRow = $keyboard[0];
        $buttonTexts = array_column($actionRow, 'text');
        $this->assertContains('Tail', $buttonTexts);
    }

    public function testBuildLogFileActionsHasClearButton(): void
    {
        $entry = new LogFileEntry('app.log', 100);

        $message = $this->builder->buildLogFileActions($entry);
        $keyboard = $this->extractKeyboard($message);

        $actionRow = $keyboard[0];
        $buttonTexts = array_column($actionRow, 'text');
        $this->assertContains('Clear', $buttonTexts);
    }

    public function testBuildLogFileActionsHasThreeActionButtons(): void
    {
        $entry = new LogFileEntry('app.log', 100);

        $message = $this->builder->buildLogFileActions($entry);
        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(3, $keyboard[0]);
    }

    public function testBuildLogFileActionsHasBackButton(): void
    {
        $entry = new LogFileEntry('app.log', 100);

        $message = $this->builder->buildLogFileActions($entry);
        $keyboard = $this->extractKeyboard($message);

        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    protected function setUp(): void
    {
        $this->builder = new LogFileActionsMessageBuilder();
    }
}
