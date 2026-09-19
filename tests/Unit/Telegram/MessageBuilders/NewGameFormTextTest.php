<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\NewGameFormText;
use DanilKashin\Localization\Language;
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
        $text = $this->displayText($this->formText->buildDateStep(null));

        $this->assertStringContainsString('Step 1 of 4', $text);
        $this->assertStringContainsString('📅 *pick a date below* 👇', $text);
        $this->assertStringContainsString('🕒 —', $text);
        $this->assertStringContainsString('📍 —', $text);
    }

    public function testDateStepHeaderCarriesNoLeadingEmoji(): void
    {
        // Regression: the header used to be prefixed with a standalone 🏐 emoji.
        $text = $this->displayText($this->formText->buildDateStep(null));

        $this->assertStringStartsWith('__*', $text);
        $this->assertStringNotContainsString('🏐', $text);
    }

    public function testDateStepCarriesForwardAnAlreadyAppliedLimit(): void
    {
        // Reachable only by navigating back from a later step; proves the limit survives
        // all the way to the very first page of the wizard, not just the confirm/location hop.
        $text = $this->displayText($this->formText->buildDateStep(8));

        $this->assertStringContainsString('👥 8 players per net', $text);
    }

    public function testTimeStepShowsStepTwoWithPickedDateAndActiveTimeField(): void
    {
        $text = $this->displayText($this->formText->buildTimeStep($this->date, null));

        $this->assertStringContainsString('Step 2 of 4', $text);
        $this->assertStringContainsString('📅 Thursday, 31.12', $text);
        $this->assertStringContainsString('🕒 *pick a time below* 👇', $text);
        $this->assertStringContainsString('📍 —', $text);
    }

    public function testTimeStepCarriesForwardAnAlreadyAppliedLimit(): void
    {
        $text = $this->displayText($this->formText->buildTimeStep($this->date, 8));

        $this->assertStringContainsString('👥 8 players per net', $text);
    }

    public function testVenueStepShowsStepThreeWithPickedDateAndTimeAndActiveVenueField(): void
    {
        $text = $this->displayText($this->formText->buildVenueStep($this->date, self::TIME, null));

        $this->assertStringContainsString('Step 3 of 4', $text);
        $this->assertStringContainsString('📅 Thursday, 31.12', $text);
        $this->assertStringContainsString('🕒 ' . self::TIME, $text);
        $this->assertStringContainsString('📍 *pick a location below* 👇', $text);
    }

    public function testVenueStepOmitsThePlayersRowWhenNoLimitWasApplied(): void
    {
        $text = $this->displayText($this->formText->buildVenueStep($this->date, self::TIME, null));

        $this->assertStringNotContainsString('👥', $text);
    }

    public function testVenueStepCarriesForwardAnAlreadyAppliedLimit(): void
    {
        // So Back-and-forth to fix the venue does not silently drop it, the same way the
        // date and time already survive that round trip.
        $text = $this->displayText($this->formText->buildVenueStep($this->date, self::TIME, 8));

        $this->assertStringContainsString('👥 8 players per net', $text);
    }

    public function testConfirmStepShowsStepFourWithAllThreePickedValues(): void
    {
        $text = $this->displayText($this->formText->buildConfirmStep($this->date, self::TIME, self::VENUE, null));

        $this->assertStringContainsString('Step 4 of 4', $text);
        $this->assertStringContainsString('📅 Thursday, 31.12', $text);
        $this->assertStringContainsString('🕒 ' . self::TIME, $text);
        $this->assertStringContainsString('📍 ' . self::VENUE, $text);
    }

    public function testConfirmStepOmitsTheVenueRowWhenNoVenueWasPicked(): void
    {
        $text = $this->displayText($this->formText->buildConfirmStep($this->date, self::TIME, null, null));

        $this->assertStringNotContainsString('📍', $text);
    }

    public function testConfirmStepOmitsThePlayersRowWhenNoLimitIsApplied(): void
    {
        $text = $this->displayText($this->formText->buildConfirmStep($this->date, self::TIME, self::VENUE, null));

        $this->assertStringNotContainsString('👥', $text);
    }

    public function testConfirmStepShowsThePlayersRowAfterLocationWhenALimitIsApplied(): void
    {
        $text = $this->displayText($this->formText->buildConfirmStep($this->date, self::TIME, self::VENUE, 6));

        $this->assertStringContainsString('📍 ' . self::VENUE . "\n👥 6 players per net", $text);
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
        $text = $this->displayText($this->formText->buildGameTitle($this->date, self::TIME, self::VENUE, null));

        $this->assertStringNotContainsString('Step', $text);
        $this->assertStringContainsString('📅 Thursday, 31.12', $text);
        $this->assertStringContainsString('🕒 ' . self::TIME, $text);
        $this->assertStringContainsString('📍 ' . self::VENUE, $text);
    }

    public function testGameTitleOmitsTheVenueRowWhenNoVenueWasPicked(): void
    {
        $text = $this->displayText($this->formText->buildGameTitle($this->date, self::TIME, null, null));

        $this->assertStringNotContainsString('📍', $text);
    }

    public function testGameTitleOmitsThePlayersRowWhenNoLimitIsApplied(): void
    {
        $text = $this->displayText($this->formText->buildGameTitle($this->date, self::TIME, self::VENUE, null));

        $this->assertStringNotContainsString('👥', $text);
    }

    public function testGameTitleCarriesThePlayersPerNetPhraseWhenALimitIsApplied(): void
    {
        $text = $this->displayText($this->formText->buildGameTitle($this->date, self::TIME, self::VENUE, 6));

        $this->assertStringContainsString('👥 6 players per net', $text);
    }

    public function testValuesAreEscapedForMarkdownV2(): void
    {
        $text = $this->formText->buildConfirmStep($this->date, self::TIME, 'Sant Sebastia (court 2)', null);

        $this->assertStringContainsString('Sant Sebastia \\(court 2\\)', $text);
    }

    public function testTheDateIsSpelledInTheReadersLanguage(): void
    {
        $formText = new NewGameFormText(new Translator(Language::RU, tempnam(sys_get_temp_dir(), 'bvb_missing_')));

        $this->assertStringContainsString(
            '📅 Четверг, 31.12',
            $this->displayText($formText->buildConfirmStep($this->date, self::TIME, self::VENUE, null)),
        );
        $this->assertStringContainsString(
            '📅 Четверг, 31.12',
            $this->displayText($formText->buildGameTitle($this->date, self::TIME, self::VENUE, null)),
        );
    }

    public function testThePlayersPerNetPhraseIsSpelledInTheReadersLanguage(): void
    {
        $formText = new NewGameFormText(new Translator(Language::RU, tempnam(sys_get_temp_dir(), 'bvb_missing_')));

        $this->assertStringContainsString(
            '👥 6 игроков на сетку',
            $this->displayText($formText->buildConfirmStep($this->date, self::TIME, self::VENUE, 6)),
        );
    }

    private function displayText(string $text): string
    {
        return str_replace('\\', '', $text);
    }
}
