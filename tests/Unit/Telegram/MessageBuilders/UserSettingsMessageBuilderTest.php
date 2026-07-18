<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\UserSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use PHPUnit\Framework\TestCase;

final class UserSettingsMessageBuilderTest extends TestCase
{
    private UserSettingsMessageBuilder $builder;

    public function testShowsGameIdInHeader(): void
    {
        $message = $this->build(gameId: 42, slotCount: 2, volleyball: 1, net: 1);

        $this->assertStringContainsString('#42', $message->getText()->getMessageText());
    }

    // --- buildUserSettings ---

    public function testShowsUserName(): void
    {
        $message = $this->build(userName: 'Alice');

        $this->assertStringContainsString('Alice', $message->getText()->getMessageText());
    }

    public function testShowsUserLinkWhenProvided(): void
    {
        $message = $this->build(userName: 'Alice', userLink: 'https://t.me/alice');

        $text = $message->getText()->getMessageText();
        $this->assertStringContainsString('Alice', $text);
        $this->assertStringContainsString('https://t.me/alice', $text);
    }

    public function testOmitsUserLinkWhenNotProvided(): void
    {
        $message = $this->build(userName: 'Alice', userLink: null);

        $this->assertStringNotContainsString('https://t.me/', $message->getText()->getMessageText());
    }

    public function testShowsUserId(): void
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

    // --- buildUserNotFound ---

    public function testHasBackButton(): void
    {
        $message = $this->build();
        $keyboard = $this->extractKeyboard($message);

        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    public function testUserNotFoundShowsMessage(): void
    {
        $message = $this->builder->buildUserNotFound(42);

        $this->assertStringContainsString('User not found', $message->getText()->getMessageText());
    }

    public function testUserNotFoundShowsGameId(): void
    {
        $message = $this->builder->buildUserNotFound(42);

        $this->assertStringContainsString('#42', $message->getText()->getMessageText());
    }

    // --- helpers ---

    public function testUserNotFoundHasBackButton(): void
    {
        $message = $this->builder->buildUserNotFound(42);
        $keyboard = $this->extractKeyboard($message);

        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    private function build(
        int $gameId = 1,
        int $telegramUserId = 100,
        string $userName = 'Alice',
        ?string $userLink = null,
        int $slotCount = 1,
        int $volleyball = 0,
        int $net = 0,
    ): TelegramMessage {
        return $this->builder->buildUserSettings(
            $gameId,
            $telegramUserId,
            $userName,
            $userLink,
            $slotCount,
            $volleyball,
            $net,
        );
    }

    protected function setUp(): void
    {
        $this->builder = new UserSettingsMessageBuilder();
    }
}
