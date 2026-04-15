<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\PlayerSettingsMessageBuilder;
use PHPUnit\Framework\TestCase;

final class PlayerSettingsMessageBuilderTest extends TestCase
{
    private PlayerSettingsMessageBuilder $builder;

    public function testShowsGameIdInHeader(): void
    {
        $message = $this->builder->buildPlayerSettings(42, 100, 'Alice', 2, 1, 1);

        $this->assertStringContainsString('#42', $message->getText()->getMessageText());
    }

    // --- buildPlayerSettings ---

    public function testShowsPlayerName(): void
    {
        $message = $this->builder->buildPlayerSettings(1, 100, 'Alice', 1, 0, 0);

        $this->assertStringContainsString('Alice', $message->getText()->getMessageText());
    }

    public function testShowsSlotCount(): void
    {
        $message = $this->builder->buildPlayerSettings(1, 100, 'Alice', 3, 0, 0);

        $this->assertStringContainsString('Slots: 3', $message->getText()->getMessageText());
    }

    public function testShowsVolleyballCount(): void
    {
        $message = $this->builder->buildPlayerSettings(1, 100, 'Alice', 1, 2, 0);

        $this->assertStringContainsString('Volleyball: 2', $message->getText()->getMessageText());
    }

    public function testShowsNetCount(): void
    {
        $message = $this->builder->buildPlayerSettings(1, 100, 'Alice', 1, 0, 3);

        $this->assertStringContainsString('Net: 3', $message->getText()->getMessageText());
    }

    public function testHasRemoveSlotButtonWhenSlotsExist(): void
    {
        $message = $this->builder->buildPlayerSettings(1, 100, 'Alice', 2, 0, 0);
        $keyboard = $this->extractKeyboard($message);

        $allButtonTexts = $this->flattenButtonTexts($keyboard);
        $this->assertContains('Remove Slot', $allButtonTexts);
    }

    private function extractKeyboard($message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
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

    public function testHidesRemoveSlotButtonWhenNoSlots(): void
    {
        $message = $this->builder->buildPlayerSettings(1, 100, 'Alice', 0, 0, 0);
        $keyboard = $this->extractKeyboard($message);

        $allButtonTexts = $this->flattenButtonTexts($keyboard);
        $this->assertNotContains('Remove Slot', $allButtonTexts);
    }

    public function testHasVolleyballButtons(): void
    {
        $message = $this->builder->buildPlayerSettings(1, 100, 'Alice', 1, 0, 0);
        $keyboard = $this->extractKeyboard($message);

        $allButtonTexts = $this->flattenButtonTexts($keyboard);
        $this->assertContains("-\u{1F3D0}", $allButtonTexts);
        $this->assertContains("+\u{1F3D0}", $allButtonTexts);
    }

    public function testHasNetButtons(): void
    {
        $message = $this->builder->buildPlayerSettings(1, 100, 'Alice', 1, 0, 0);
        $keyboard = $this->extractKeyboard($message);

        $allButtonTexts = $this->flattenButtonTexts($keyboard);
        $this->assertContains("-\u{1F578}\u{FE0F}", $allButtonTexts);
        $this->assertContains("+\u{1F578}\u{FE0F}", $allButtonTexts);
    }

    // --- buildPlayerNotFound ---

    public function testHasBackButton(): void
    {
        $message = $this->builder->buildPlayerSettings(1, 100, 'Alice', 1, 0, 0);
        $keyboard = $this->extractKeyboard($message);

        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    public function testPlayerNotFoundShowsMessage(): void
    {
        $message = $this->builder->buildPlayerNotFound(42);

        $this->assertStringContainsString('Player not found', $message->getText()->getMessageText());
    }

    public function testPlayerNotFoundShowsGameId(): void
    {
        $message = $this->builder->buildPlayerNotFound(42);

        $this->assertStringContainsString('#42', $message->getText()->getMessageText());
    }

    // --- helpers ---

    public function testPlayerNotFoundHasBackButton(): void
    {
        $message = $this->builder->buildPlayerNotFound(42);
        $keyboard = $this->extractKeyboard($message);

        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    protected function setUp(): void
    {
        $this->builder = new PlayerSettingsMessageBuilder();
    }
}
