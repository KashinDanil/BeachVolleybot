<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\SettingsMessageBuilder;
use BeachVolleybot\User\Role;
use PHPUnit\Framework\TestCase;

final class SettingsMessageBuilderTest extends TestCase
{
    private SettingsMessageBuilder $builder;

    public function testMainMenuContainsSettingsHeader(): void
    {
        $message = $this->builder->buildMainMenu(Role::Root);

        $this->assertStringContainsString('Settings', $message->getText()->getMessageText());
    }

    public function testRootSeesLogsButton(): void
    {
        $message = $this->builder->buildMainMenu(Role::Root);
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Logs', $keyboard[0][0]['text']);
    }

    public function testRootSeesGamesButton(): void
    {
        $message = $this->builder->buildMainMenu(Role::Root);
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Games', $keyboard[1][0]['text']);
    }

    public function testRootSeesTwoButtonRows(): void
    {
        $message = $this->builder->buildMainMenu(Role::Root);
        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(2, $keyboard);
    }

    public function testAdminSeesOnlyGamesButton(): void
    {
        $message = $this->builder->buildMainMenu(Role::Admin);
        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(1, $keyboard);
        $this->assertSame('Games', $keyboard[0][0]['text']);
    }

    public function testAdminDoesNotSeeLogsButton(): void
    {
        $message = $this->builder->buildMainMenu(Role::Admin);

        $this->assertNotContains('Logs', $this->keyboardLabels($message));
    }

    public function testMenuAlwaysIncludesGamesBaseline(): void
    {
        // Even the lowest role yields the Games baseline, so the keyboard is
        // never empty (an empty inline keyboard would be rejected by Telegram).
        $message = $this->builder->buildMainMenu(Role::Player);
        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(1, $keyboard);
        $this->assertSame('Games', $keyboard[0][0]['text']);
    }

    private function extractKeyboard($message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }

    /** @return list<string> */
    private function keyboardLabels($message): array
    {
        $rows = $this->extractKeyboard($message);

        return empty($rows) ? [] : array_column(array_merge(...$rows), 'text');
    }

    protected function setUp(): void
    {
        $this->builder = new SettingsMessageBuilder();
    }
}
