<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\PlayersPerNetSelection;
use BeachVolleybot\Telegram\MessageBuilders\NewGame\NewGameConfirmMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DanilKashin\Localization\Language;
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

    public function testPostButtonIsSuccessStyled(): void
    {
        $keyboard = $this->extractKeyboard($this->build(self::VENUE));
        $postRow = $keyboard[0];

        $this->assertSame('success', $postRow[0]['style']);
        $this->assertSame(NewGameCallbackAction::Send, NewGameCallbackData::fromJson($postRow[0]['callback_data'])->getAction());
    }

    public function testPostButtonCarriesNoVenue(): void
    {
        // Send fires only from the confirm page, so it reads the venue back out of the
        // text — the same way it already reads date, time and the players-per-net limit.
        $keyboard = $this->extractKeyboard($this->build(self::VENUE));
        $postRow = $keyboard[0];

        $this->assertNull(NewGameCallbackData::fromJson($postRow[0]['callback_data'])->getVenueName());
    }

    public function testBackRowReturnsToTheLocationStep(): void
    {
        $keyboard = $this->extractKeyboard($this->build(self::VENUE));
        $backRow = $keyboard[3];

        $this->assertStringContainsString('Back', $backRow[0]['text']);

        $back = NewGameCallbackData::fromJson($backRow[0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::ShowVenuePage, $back->getAction());
    }

    public function testBackRowCarriesNoLimit(): void
    {
        // An applied limit already rides in this page's own 👥 text row; NewGameVenuePageProcessor
        // reads it back from there when Back is tapped, the same way it reads date and time —
        // the Back button itself needs nothing extra on its callback.
        $keyboard = $this->extractKeyboard($this->build(self::VENUE, PlayersPerNetSelection::applied(8)));
        $backRow = $keyboard[3];

        $this->assertNull(NewGameCallbackData::fromJson($backRow[0]['callback_data'])->getPlayersPerNet());
    }

    public function testLanguageRowOffersEveryLanguageButTheOneInForce(): void
    {
        $keyboard = $this->extractKeyboard($this->build(self::VENUE));
        $labels = array_column($keyboard[2], 'text');

        $expected = array_values(array_diff(Translator::supportedLanguages(), [Language::EN]));

        $this->assertSame(array_map(ucfirst(...), $expected), $labels);
    }

    public function testLanguageButtonCarriesTheLanguageItSwitchesToButNoVenue(): void
    {
        // A language switch only ever fires while already on the confirm page, so the
        // processor reads the venue back out of the text, the same way it reads date and time.
        $keyboard = $this->extractKeyboard($this->build(self::VENUE));

        $callbackData = NewGameCallbackData::fromJson($keyboard[2][0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::SetLanguage, $callbackData->getAction());
        $this->assertNotSame(Language::EN, $callbackData->getLanguage());
        $this->assertNull($callbackData->getVenueName());
    }

    public function testLanguageButtonCarriesNoVenueWhenNoneWasPicked(): void
    {
        $keyboard = $this->extractKeyboard($this->build(null));

        $this->assertNull(NewGameCallbackData::fromJson($keyboard[2][0]['callback_data'])->getVenueName());
    }

    public function testLanguageButtonCarriesTheWorkingValueEvenWhilePending(): void
    {
        // Unlike the venue, a pending value never makes it into the text, so switching
        // language without this would silently reset it back to the default.
        $keyboard = $this->extractKeyboard($this->build(self::VENUE, PlayersPerNetSelection::pending(10)));

        $this->assertSame(10, NewGameCallbackData::fromJson($keyboard[2][0]['callback_data'])->getPlayersPerNet());
    }

    public function testLanguageButtonCarriesTheAppliedValue(): void
    {
        $keyboard = $this->extractKeyboard($this->build(self::VENUE, PlayersPerNetSelection::applied(8)));

        $this->assertSame(8, NewGameCallbackData::fromJson($keyboard[2][0]['callback_data'])->getPlayersPerNet());
    }

    public function testARussianWizardPostsInRussianAndOffersTheRestOfTheLanguages(): void
    {
        $message = new NewGameConfirmMessageBuilder(self::russian())
            ->build(new DateTimeImmutable('2099-12-31'), self::TIME, self::VENUE, self::unset());
        $keyboard = $this->extractKeyboard($message);

        $this->assertStringContainsString('Четверг, 31.12', $this->displayText($message));
        $this->assertSame('Опубликовать', $keyboard[0][0]['text']);
        $this->assertNotContains('Ru', array_column($keyboard[2], 'text'));
        $this->assertContains('En', array_column($keyboard[2], 'text'));
    }

    public function testEveryButtonCarriesTheChosenLanguage(): void
    {
        $keyboard = $this->extractKeyboard(
            new NewGameConfirmMessageBuilder(self::russian())
                ->build(new DateTimeImmutable('2099-12-31'), self::TIME, self::VENUE, self::unset()),
        );

        foreach ([$keyboard[0][0], ...$keyboard[1], ...$keyboard[3]] as $button) {
            $language = NewGameCallbackData::fromJson($button['callback_data'])->getLanguage();

            $this->assertSame(Language::RU, $language, "'{$button['text']}' carries no language");
        }
    }

    // --- players-per-net row ---

    public function testPlayersPerNetRowSitsBetweenPostAndLanguageRows(): void
    {
        $keyboard = $this->extractKeyboard($this->build(self::VENUE, self::unset()));
        $labels = array_column($keyboard[1], 'text');

        $this->assertSame(['←', '👥 6', '→'], $labels);
    }

    public function testDecreaseButtonIsOmittedAtTheFloor(): void
    {
        $keyboard = $this->extractKeyboard($this->build(self::VENUE, PlayersPerNetSelection::pending(4)));
        $labels = array_column($keyboard[1], 'text');

        $this->assertSame(['👥 4', '→'], $labels);
    }

    public function testIncreaseButtonIsOmittedAtTheCeiling(): void
    {
        $keyboard = $this->extractKeyboard($this->build(self::VENUE, PlayersPerNetSelection::pending(12)));
        $labels = array_column($keyboard[1], 'text');

        $this->assertSame(['←', '👥 12'], $labels);
    }

    public function testArrowButtonsCarryTheAlreadyAdjustedTargetValue(): void
    {
        $keyboard = $this->extractKeyboard($this->build(self::VENUE, PlayersPerNetSelection::pending(6)));
        [$decrease, , $increase] = $keyboard[1];

        $decreaseData = NewGameCallbackData::fromJson($decrease['callback_data']);
        $increaseData = NewGameCallbackData::fromJson($increase['callback_data']);

        $this->assertSame(NewGameCallbackAction::AdjustPlayersPerNet, $decreaseData->getAction());
        $this->assertSame(5, $decreaseData->getPlayersPerNet());
        $this->assertSame(NewGameCallbackAction::AdjustPlayersPerNet, $increaseData->getAction());
        $this->assertSame(7, $increaseData->getPlayersPerNet());
    }

    public function testMiddleButtonSetsTheLimitWhilePending(): void
    {
        $keyboard = $this->extractKeyboard($this->build(self::VENUE, PlayersPerNetSelection::pending(8)));
        $middle = $keyboard[1][1];

        $this->assertSame('👥 8', $middle['text']);

        $callbackData = NewGameCallbackData::fromJson($middle['callback_data']);
        $this->assertSame(NewGameCallbackAction::SetPlayersPerNet, $callbackData->getAction());
        $this->assertSame(8, $callbackData->getPlayersPerNet());
    }

    public function testMiddleButtonRemovesTheLimitWhileApplied(): void
    {
        $keyboard = $this->extractKeyboard($this->build(self::VENUE, PlayersPerNetSelection::applied(8)));
        $middle = $keyboard[1][1];

        $this->assertSame('🗑', $middle['text']);

        $callbackData = NewGameCallbackData::fromJson($middle['callback_data']);
        $this->assertSame(NewGameCallbackAction::RemovePlayersPerNet, $callbackData->getAction());
        $this->assertSame(8, $callbackData->getPlayersPerNet());
    }

    public function testPlayersRowAppearsOnlyOnceTheLimitIsApplied(): void
    {
        $pendingText = $this->displayText($this->build(self::VENUE, PlayersPerNetSelection::pending(8)));
        $appliedText = $this->displayText($this->build(self::VENUE, PlayersPerNetSelection::applied(8)));

        $this->assertStringNotContainsString('👥', $pendingText);
        $this->assertStringContainsString('👥 8 spots per net', $appliedText);
    }

    public function testPlayersPerNetButtonsCarryNoVenueButKeepTheLanguage(): void
    {
        // These buttons only ever fire while already on the confirm page, so the processor
        // reads the venue back out of the text — no need to round-trip it on the callback.
        $keyboard = $this->extractKeyboard(
            new NewGameConfirmMessageBuilder(self::russian())
                ->build(new DateTimeImmutable('2099-12-31'), self::TIME, self::VENUE, PlayersPerNetSelection::applied(6)),
        );

        foreach ($keyboard[1] as $button) {
            $callbackData = NewGameCallbackData::fromJson($button['callback_data']);

            $this->assertNull($callbackData->getVenueName(), "'{$button['text']}' unexpectedly carries the venue");
            $this->assertSame(Language::RU, $callbackData->getLanguage(), "'{$button['text']}' lost the language");
        }
    }

    private static function unset(): PlayersPerNetSelection
    {
        return PlayersPerNetSelection::pending(PlayersPerNetSelection::DEFAULT);
    }

    private static function russian(): Translator
    {
        return new Translator(Language::RU, tempnam(sys_get_temp_dir(), 'bvb_missing_'));
    }

    private function build(?string $venueName, ?PlayersPerNetSelection $selection = null): TelegramMessage
    {
        return $this->builder->build(new DateTimeImmutable('2099-12-31'), self::TIME, $venueName, $selection ?? self::unset());
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
