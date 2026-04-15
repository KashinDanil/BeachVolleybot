<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\SettingsMessageBuilder;
use PHPUnit\Framework\TestCase;

final class SettingsMessageBuilderTest extends TestCase
{
    private SettingsMessageBuilder $builder;

    public function testMainMenuContainsSettingsHeader(): void
    {
        $message = $this->builder->buildMainMenu();

        $this->assertStringContainsString('Settings', $message->getText()->getMessageText());
    }

    public function testMainMenuHasLogsButton(): void
    {
        $message = $this->builder->buildMainMenu();
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Logs', $keyboard[0][0]['text']);
    }

    private function extractKeyboard($message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }

    public function testMainMenuHasGamesButton(): void
    {
        $message = $this->builder->buildMainMenu();
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Games', $keyboard[1][0]['text']);
    }

    public function testMainMenuHasTwoButtonRows(): void
    {
        $message = $this->builder->buildMainMenu();
        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(2, $keyboard);
    }

    protected function setUp(): void
    {
        $this->builder = new SettingsMessageBuilder();
    }
}
