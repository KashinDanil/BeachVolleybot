<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\NewGameFormText;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class NewGameFormTextTest extends TestCase
{
    private const string TIME = '18:30';
    private const string VENUE = 'Bogatell';

    private NewGameFormText $formText;
    private DateTimeImmutable $date;

    protected function setUp(): void
    {
        $this->formText = new NewGameFormText(new Translator());
        $this->date = new DateTimeImmutable('2099-12-31');
    }

    public function testDateStepShowsStepOneWithActiveDateFieldAndEmptyRest(): void
    {
        $text = $this->displayText($this->formText->buildDateStep());

        $this->assertStringContainsString('Step 1 of 4', $text);
        $this->assertStringContainsString('📅 *pick a date below* 👇', $text);
        $this->assertStringContainsString('🕒 —', $text);
        $this->assertStringContainsString('📍 —', $text);
    }

    public function testDateStepHeaderCarriesNoLeadingEmoji(): void
    {
        // Regression: the header used to be prefixed with a standalone 🏐 emoji.
        $text = $this->displayText($this->formText->buildDateStep());

        $this->assertStringStartsWith('__*', $text);
        $this->assertStringNotContainsString('🏐', $text);
    }

    public function testTimeStepShowsStepTwoWithPickedDateAndActiveTimeField(): void
    {
        $text = $this->displayText($this->formText->buildTimeStep($this->date));

        $this->assertStringContainsString('Step 2 of 4', $text);
        $this->assertStringContainsString('📅 Thursday, 31.12', $text);
        $this->assertStringContainsString('🕒 *pick a time below* 👇', $text);
        $this->assertStringContainsString('📍 —', $text);
    }

    public function testLocationStepShowsStepThreeWithPickedDateAndTimeAndActiveLocationField(): void
    {
        $text = $this->displayText($this->formText->buildLocationStep($this->date, self::TIME));

        $this->assertStringContainsString('Step 3 of 4', $text);
        $this->assertStringContainsString('📅 Thursday, 31.12', $text);
        $this->assertStringContainsString('🕒 ' . self::TIME, $text);
        $this->assertStringContainsString('📍 *pick a location below* 👇', $text);
    }

    public function testConfirmStepShowsStepFourWithAllThreePickedValues(): void
    {
        $text = $this->displayText($this->formText->buildConfirmStep($this->date, self::TIME, self::VENUE));

        $this->assertStringContainsString('Step 4 of 4', $text);
        $this->assertStringContainsString('📅 Thursday, 31.12', $text);
        $this->assertStringContainsString('🕒 ' . self::TIME, $text);
        $this->assertStringContainsString('📍 ' . self::VENUE, $text);
    }

    public function testConfirmStepOmitsTheLocationRowWhenNoVenueWasPicked(): void
    {
        $text = $this->displayText($this->formText->buildConfirmStep($this->date, self::TIME, null));

        $this->assertStringNotContainsString('📍', $text);
    }

    public function testSuccessShowsSuccessEmojiHeaderAndPostedMessage(): void
    {
        $text = $this->displayText($this->formText->buildSuccess());

        $this->assertStringStartsWith('✅ __*', $text);
        $this->assertStringContainsString('Game created!', $text);
        $this->assertStringContainsString('The game message has been posted to this chat.', $text);
    }

    public function testGameTitleRendersFieldRowsWithoutAHeader(): void
    {
        $text = $this->displayText($this->formText->buildGameTitle($this->date, self::TIME, self::VENUE));

        $this->assertStringNotContainsString('Step', $text);
        $this->assertStringContainsString('📅 Thursday, 31.12', $text);
        $this->assertStringContainsString('🕒 ' . self::TIME, $text);
        $this->assertStringContainsString('📍 ' . self::VENUE, $text);
    }

    public function testGameTitleOmitsTheLocationRowWhenNoVenueWasPicked(): void
    {
        $text = $this->displayText($this->formText->buildGameTitle($this->date, self::TIME, null));

        $this->assertStringNotContainsString('📍', $text);
    }

    public function testValuesAreEscapedForMarkdownV2(): void
    {
        $text = $this->formText->buildConfirmStep($this->date, self::TIME, 'Sant Sebastia (court 2)');

        $this->assertStringContainsString('Sant Sebastia \\(court 2\\)', $text);
    }

    private function displayText(string $text): string
    {
        return str_replace('\\', '', $text);
    }
}
