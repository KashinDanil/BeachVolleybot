<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Log\LogFileEntry;
use BeachVolleybot\Telegram\MessageBuilders\KeyboardPagination;
use BeachVolleybot\Telegram\MessageBuilders\LogsListMessageBuilder;
use PHPUnit\Framework\TestCase;

final class LogsListMessageBuilderTest extends TestCase
{
    private LogsListMessageBuilder $builder;

    public function testLogsListContainsHeader(): void
    {
        $pagination = new KeyboardPagination(totalItems: 0, perPage: 8, page: 1);

        $message = $this->builder->buildLogsList([], $pagination);

        $this->assertStringContainsString('Logs', $message->getText()->getMessageText());
    }

    // --- header ---

    public function testLogsListShowsFileButtonsForEntries(): void
    {
        $entries = [
            new LogFileEntry('app.log', 1024),
            new LogFileEntry('verbose.log', 2048),
        ];
        $pagination = new KeyboardPagination(totalItems: 2, perPage: 8, page: 1);

        $message = $this->builder->buildLogsList($entries, $pagination);
        $keyboard = $this->extractKeyboard($message);

        $this->assertStringContainsString('app.log', $keyboard[0][0]['text']);
        $this->assertStringContainsString('verbose.log', $keyboard[1][0]['text']);
    }

    // --- entries ---

    private function extractKeyboard($message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }

    public function testLogsListShowsFileSizeInButtonLabel(): void
    {
        $entries = [new LogFileEntry('app.log', 1536)];
        $pagination = new KeyboardPagination(totalItems: 1, perPage: 8, page: 1);

        $message = $this->builder->buildLogsList($entries, $pagination);
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('app.log (1.5 KB)', $keyboard[0][0]['text']);
    }

    // --- pagination ---

    public function testLogsListHidesPageInfoOnSinglePage(): void
    {
        $entries = [new LogFileEntry('app.log', 100)];
        $pagination = new KeyboardPagination(totalItems: 1, perPage: 8, page: 1);

        $message = $this->builder->buildLogsList($entries, $pagination);

        $this->assertStringNotContainsString('Page', $message->getText()->getMessageText());
    }

    public function testLogsListShowsPageInfoOnMultiplePages(): void
    {
        $entries = [new LogFileEntry('app.log', 100)];
        $pagination = new KeyboardPagination(totalItems: 20, perPage: 8, page: 1);

        $message = $this->builder->buildLogsList($entries, $pagination);

        $this->assertStringContainsString('Page 1 of 3', $message->getText()->getMessageText());
    }

    public function testLogsListShowsPaginationButtonsOnMultiplePages(): void
    {
        $entries = [new LogFileEntry('app.log', 100)];
        $pagination = new KeyboardPagination(totalItems: 20, perPage: 8, page: 1);

        $message = $this->builder->buildLogsList($entries, $pagination);
        $keyboard = $this->extractKeyboard($message);

        $allButtonTexts = $this->flattenButtonTexts($keyboard);
        $this->assertContains('Next >>', $allButtonTexts);
    }

    private function flattenButtonTexts(array $keyboard): array
    {
        $texts = [];
        foreach ($keyboard as $row) {
            foreach ($row as $button) {
                $texts[] = $button['text'];
            }
        }

        return $texts;
    }

    // --- back button ---

    public function testLogsListHidesPaginationOnSinglePage(): void
    {
        $entries = [new LogFileEntry('app.log', 100)];
        $pagination = new KeyboardPagination(totalItems: 1, perPage: 8, page: 1);

        $message = $this->builder->buildLogsList($entries, $pagination);
        $keyboard = $this->extractKeyboard($message);

        $allButtonTexts = $this->flattenButtonTexts($keyboard);
        $this->assertNotContains('<< Prev', $allButtonTexts);
        $this->assertNotContains('Next >>', $allButtonTexts);
    }

    // --- helpers ---

    public function testLogsListHasBackButton(): void
    {
        $pagination = new KeyboardPagination(totalItems: 0, perPage: 8, page: 1);

        $message = $this->builder->buildLogsList([], $pagination);
        $keyboard = $this->extractKeyboard($message);

        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    protected function setUp(): void
    {
        $this->builder = new LogsListMessageBuilder();
    }
}
