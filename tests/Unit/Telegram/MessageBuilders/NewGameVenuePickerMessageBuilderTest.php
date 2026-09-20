<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Common\GameDateResolver;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\NewGameVenuePickerMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\NewGameTimePickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Weather\Location\KnownVenues;
use DanilKashin\Localization\Language;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class NewGameVenuePickerMessageBuilderTest extends TestCase
{
    private const string TIME = '18:30';

    private NewGameVenuePickerMessageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new NewGameVenuePickerMessageBuilder(new Translator());
    }

    public function testFirstPageHasFiveVenuesThenSkipThenNextOnly(): void
    {
        $keyboard = $this->extractKeyboard($this->build(1));

        $this->assertCount(8, $keyboard); // 5 venue rows + skip row + nav row + back row
        $paginationRow = $this->navigationRow($keyboard);
        $this->assertCount(1, $paginationRow);
        $this->assertStringContainsString('Next', $paginationRow[0]['text']);
    }

    public function testLastPageHasRemainderThenPrevOnly(): void
    {
        $totalVenues = count(KnownVenues::all());
        $lastPage = (int) ceil($totalVenues / 5);

        $keyboard = $this->extractKeyboard($this->build($lastPage));

        $venueRows = count($keyboard) - 3; // minus skip row + nav row + back row
        $this->assertSame($totalVenues - ($lastPage - 1) * 5, $venueRows);
        $paginationRow = $this->navigationRow($keyboard);
        $this->assertCount(1, $paginationRow);
        $this->assertStringContainsString('Prev', $paginationRow[0]['text']);
    }

    public function testSkipButtonSitsRightBeforeNav(): void
    {
        $keyboard = $this->extractKeyboard($this->build(1));

        $backRow = array_pop($keyboard);
        $navRow = array_pop($keyboard);
        $skipRow = array_pop($keyboard);

        $this->assertCount(1, $skipRow);
        $this->assertStringContainsString('Skip location', $skipRow[0]['text']);
        $this->assertStringContainsString('—', $skipRow[0]['text']);
        $this->assertStringContainsString('Next', $navRow[0]['text']);
        $this->assertStringContainsString('Back', $backRow[0]['text']);

        $callbackData = NewGameCallbackData::fromJson($skipRow[0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::SkipVenue, $callbackData->getAction());
    }

    public function testVenueButtonCarriesVenueName(): void
    {
        $keyboard = $this->extractKeyboard($this->build(2));

        $callbackData = NewGameCallbackData::fromJson($keyboard[0][0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::PickVenue, $callbackData->getAction());
        // Page 2 starts at the 6th venue; the button carries its name, not a position.
        $this->assertSame(KnownVenues::all()[5]->name, $keyboard[0][0]['text']);
        $this->assertSame($keyboard[0][0]['text'], $callbackData->getVenueName());
    }

    public function testNavCallbackAdvancesVenuePage(): void
    {
        $keyboard = $this->extractKeyboard($this->build(1));
        $paginationRow = $this->navigationRow($keyboard);

        $next = NewGameCallbackData::fromJson($paginationRow[0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::ShowVenuePage, $next->getAction());
        $this->assertSame(2, $next->getPage());
    }

    public function testBackRowReturnsToTimeStep(): void
    {
        $keyboard = $this->extractKeyboard($this->build(1));
        $backRow = $this->backRow($keyboard);

        $this->assertCount(1, $backRow);
        $this->assertStringContainsString('Back', $backRow[0]['text']);

        $back = NewGameCallbackData::fromJson($backRow[0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::ShowTimePage, $back->getAction());
        $this->assertSame(NewGameTimePickerMessageBuilder::START_PAGE, $back->getPage());
    }

    public function testFormShowsStepThreeWithDateAndTime(): void
    {
        $text = str_replace('\\', '', $this->build(1)->getText()->getMessageText());

        $this->assertStringContainsString('Step 3 of 4', $text);
        $this->assertStringContainsString('31.12', $text);
        $this->assertStringContainsString('18:30', $text);
        $this->assertStringContainsString('pick a location below', $text);
        $this->assertStringNotContainsString('posted to the chat', $text);
    }

    public function testDateAndTimeAreRecoverableFromDisplayedText(): void
    {
        // Telegram returns the callback message as plain text (MarkdownV2 escapes stripped);
        // simulate that by removing the escaping backslashes, then re-parse the running state.
        $displayText = str_replace('\\', '', $this->build(1)->getText()->getMessageText());

        $recoveredDate = GameDateResolver::resolve($displayText, new DateTimeImmutable('2099-12-01'));
        $this->assertNotNull($recoveredDate);
        $this->assertSame('31.12.2099', $recoveredDate->format('d.m.Y'));
        $this->assertSame(self::TIME, TimeExtractor::extract($displayText));
    }

    public function testRunningStateIsStillRecoverableFromATranslatedStep(): void
    {
        $displayText = str_replace('\\', '', $this->russianBuilder()->build(new DateTimeImmutable('2099-12-31'), self::TIME, 1)->getText()->getMessageText());

        $this->assertStringContainsString('Четверг, 31.12', $displayText);
        $this->assertSame('31.12.2099', GameDateResolver::resolve($displayText, new DateTimeImmutable('2099-12-01'))?->format('d.m.Y'));
        $this->assertSame(self::TIME, TimeExtractor::extract($displayText));
    }

    // --- carrying a previously applied players-per-net limit ---

    public function testNoButtonEverCarriesTheLimit(): void
    {
        // A limit already applied rides in the 👥 row of this page's own text — see
        // testCarriesForwardAnAlreadyAppliedLimit below — not on any of its buttons.
        $keyboard = $this->extractKeyboard($this->buildWithLimit(1, 8));

        foreach (array_merge(...$keyboard) as $button) {
            $this->assertNull(NewGameCallbackData::fromJson($button['callback_data'])->getPlayersPerNet(), "'{$button['text']}' unexpectedly carries the limit");
        }
    }

    public function testCarriesForwardAnAlreadyAppliedLimit(): void
    {
        $text = str_replace('\\', '', $this->buildWithLimit(1, 8)->getText()->getMessageText());

        $this->assertStringContainsString('👥 8 spots per net', $text);
    }

    public function testOmitsThePlayersRowWhenNoLimitWasApplied(): void
    {
        $text = str_replace('\\', '', $this->build(1)->getText()->getMessageText());

        $this->assertStringNotContainsString('👥', $text);
    }

    public function testEveryButtonCarriesTheChosenLanguage(): void
    {
        $keyboard = $this->extractKeyboard($this->russianBuilder()->build(new DateTimeImmutable('2099-12-31'), self::TIME, 1));

        foreach (array_merge(...$keyboard) as $button) {
            $language = NewGameCallbackData::fromJson($button['callback_data'])->getLanguage();

            $this->assertSame(Language::RU, $language, "'{$button['text']}' carries no language");
        }
    }

    private function russianBuilder(): NewGameVenuePickerMessageBuilder
    {
        return new NewGameVenuePickerMessageBuilder(new Translator(Language::RU, tempnam(sys_get_temp_dir(), 'bvb_missing_')));
    }

    private function build(int $page): TelegramMessage
    {
        return $this->builder->build(new DateTimeImmutable('2099-12-31'), self::TIME, $page);
    }

    private function buildWithLimit(int $page, int $playersPerNet): TelegramMessage
    {
        return $this->builder->build(new DateTimeImmutable('2099-12-31'), self::TIME, $page, $playersPerNet);
    }

    private function navigationRow(array $keyboard): array
    {
        return $keyboard[count($keyboard) - 2]; // pagination row sits just above the Back row
    }

    private function backRow(array $keyboard): array
    {
        return $keyboard[count($keyboard) - 1];
    }

    private function extractKeyboard(TelegramMessage $message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }
}
