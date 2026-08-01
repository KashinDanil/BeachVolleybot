<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\NewGameCreatedMessageBuilder;
use PHPUnit\Framework\TestCase;

final class NewGameCreatedMessageBuilderTest extends TestCase
{
    private NewGameCreatedMessageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new NewGameCreatedMessageBuilder(new Translator());
    }

    public function testShowsHeaderAndPostedMessage(): void
    {
        $text = str_replace('\\', '', $this->builder->build()->getText()->getMessageText());

        $this->assertStringContainsString('Game created', $text);
        $this->assertStringContainsString('posted to this chat', $text);
    }

    public function testDropsThePickedValues(): void
    {
        $text = str_replace('\\', '', $this->builder->build()->getText()->getMessageText());

        // The confirmation no longer echoes the title — the posted game message carries it.
        $this->assertStringNotContainsString('31.12', $text);
        $this->assertStringNotContainsString('18:30', $text);
        $this->assertStringNotContainsString('Bogatell', $text);
    }

    public function testHasNoButtons(): void
    {
        $keyboard = json_decode($this->builder->build()->getKeyboard()->toJson(), true)['inline_keyboard'];

        $this->assertSame([], $keyboard);
    }

    public function testDoesNotMentionGamesCommand(): void
    {
        $this->assertStringNotContainsString('/games', $this->builder->build()->getText()->getMessageText());
    }
}
