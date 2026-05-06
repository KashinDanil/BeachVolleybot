<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\PlayerSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use PHPUnit\Framework\TestCase;

final class PlayerSettingsMessageBuilderTest extends TestCase
{
    private PlayerSettingsMessageBuilder $builder;

    public function testShowsGameIdInHeader(): void
    {
        $message = $this->build(gameId: 42, slotCount: 2, volleyball: 1, net: 1);

        $this->assertStringContainsString('#42', $message->getText()->getMessageText());
    }

    // --- buildPlayerSettings ---

    public function testShowsPlayerName(): void
    {
        $message = $this->build(playerName: 'Alice');

        $this->assertStringContainsString('Alice', $message->getText()->getMessageText());
    }

    public function testShowsPlayerLinkWhenProvided(): void
    {
        $message = $this->build(playerName: 'Alice', playerLink: 'https://t.me/alice');

        $text = $message->getText()->getMessageText();
        $this->assertStringContainsString('Alice', $text);
        $this->assertStringContainsString('https://t.me/alice', $text);
    }

    public function testOmitsPlayerLinkWhenNotProvided(): void
    {
        $message = $this->build(playerName: 'Alice', playerLink: null);

        $this->assertStringNotContainsString('https://t.me/', $message->getText()->getMessageText());
    }

    public function testShowsPlayerId(): void
    {
        $message = $this->build(telegramUserId: 12345678);

        $this->assertStringContainsString('Telegram ID: 12345678', $message->getText()->getMessageText());
    }

    public function testShowsSlotCount(): void
    {
        $message = $this->build(slotCount: 3);

        $this->assertStringContainsString('Slots: 3', $message->getText()->getMessageText());
    }

    public function testShowsVolleyballCount(): void
    {
        $message = $this->build(volleyball: 2);

        $this->assertStringContainsString('Volleyball: 2', $message->getText()->getMessageText());
    }

    public function testShowsNetCount(): void
    {
        $message = $this->build(net: 3);

        $this->assertStringContainsString('Net: 3', $message->getText()->getMessageText());
    }

    public function testHasRemoveSlotButtonWhenSlotsExist(): void
    {
        $message = $this->build(slotCount: 2);
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
        $message = $this->build(slotCount: 0);
        $keyboard = $this->extractKeyboard($message);

        $allButtonTexts = $this->flattenButtonTexts($keyboard);
        $this->assertNotContains('Remove Slot', $allButtonTexts);
    }

    public function testHasVolleyballButtons(): void
    {
        $message = $this->build();
        $keyboard = $this->extractKeyboard($message);

        $allButtonTexts = $this->flattenButtonTexts($keyboard);
        $this->assertContains("-\u{1F3D0}", $allButtonTexts);
        $this->assertContains("+\u{1F3D0}", $allButtonTexts);
    }

    public function testHasNetButtons(): void
    {
        $message = $this->build();
        $keyboard = $this->extractKeyboard($message);

        $allButtonTexts = $this->flattenButtonTexts($keyboard);
        $this->assertContains("-\u{1F578}\u{FE0F}", $allButtonTexts);
        $this->assertContains("+\u{1F578}\u{FE0F}", $allButtonTexts);
    }

    // --- buildPlayerNotFound ---

    public function testHasBackButton(): void
    {
        $message = $this->build();
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

    private function build(
        int $gameId = 1,
        int $telegramUserId = 100,
        string $playerName = 'Alice',
        ?string $playerLink = null,
        int $slotCount = 1,
        int $volleyball = 0,
        int $net = 0,
    ): TelegramMessage {
        return $this->builder->buildPlayerSettings(
            $gameId,
            $telegramUserId,
            $playerName,
            $playerLink,
            $slotCount,
            $volleyball,
            $net,
        );
    }

    protected function setUp(): void
    {
        $this->builder = new PlayerSettingsMessageBuilder();
    }
}
