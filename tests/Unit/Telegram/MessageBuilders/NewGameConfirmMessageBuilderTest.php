<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\NewGameConfirmMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class NewGameConfirmMessageBuilderTest extends TestCase
{
    private const string TIME = '18:30';
    private const string VENUE = 'Bogatell';

    private NewGameConfirmMessageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new NewGameConfirmMessageBuilder(new Translator());
    }

    public function testFormShowsStepFourWithAllThreePickedValues(): void
    {
        $text = $this->displayText($this->build(self::VENUE));

        $this->assertStringContainsString('Step 4 of 4', $text);
        $this->assertStringContainsString('31.12', $text);
        $this->assertStringContainsString(self::TIME, $text);
        $this->assertStringContainsString(self::VENUE, $text);
    }

    public function testOmitsTheLocationLineWhenNoVenueWasPicked(): void
    {
        // The confirm page mirrors the game message it's about to post — no venue picked
        // means no location line, not a placeholder saying so.
        $text = $this->displayText($this->build(null));

        $this->assertStringNotContainsString('Skip location', $text);
        $this->assertStringNotContainsString('📍', $text);
    }

    public function testPostButtonIsSuccessStyledAndCarriesTheVenueName(): void
    {
        $keyboard = $this->extractKeyboard($this->build(self::VENUE));
        $postRow = $keyboard[0];

        $this->assertSame('success', $postRow[0]['style']);

        $callbackData = NewGameCallbackData::fromJson($postRow[0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::Send, $callbackData->getAction());
        $this->assertSame(self::VENUE, $callbackData->getVenueName());
    }

    public function testPostButtonCarriesNoVenueWhenSkipped(): void
    {
        $keyboard = $this->extractKeyboard($this->build(null));
        $postRow = $keyboard[0];

        $callbackData = NewGameCallbackData::fromJson($postRow[0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::Send, $callbackData->getAction());
        $this->assertNull($callbackData->getVenueName());
    }

    public function testBackRowReturnsToTheLocationStep(): void
    {
        $keyboard = $this->extractKeyboard($this->build(self::VENUE));
        $backRow = $keyboard[1];

        $this->assertStringContainsString('Back', $backRow[0]['text']);

        $back = NewGameCallbackData::fromJson($backRow[0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::ShowVenuePage, $back->getAction());
    }

    private function build(?string $venueName): TelegramMessage
    {
        return $this->builder->build(new DateTimeImmutable('2099-12-31'), self::TIME, $venueName);
    }

    private function displayText(TelegramMessage $message): string
    {
        return str_replace('\\', '', $message->getText()->getMessageText());
    }

    private function extractKeyboard(TelegramMessage $message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }
}
