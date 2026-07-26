<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\NewGameTimePickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class NewGameTimePickerMessageBuilderTest extends TestCase
{
    private NewGameTimePickerMessageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new NewGameTimePickerMessageBuilder(new Translator());
    }

    public function testStartPageShowsSixHourRowsPlusNavAndBack(): void
    {
        $keyboard = $this->extractKeyboard($this->buildStartPage());

        $this->assertCount(8, $keyboard); // 6 hour rows + nav row + back row
        $this->assertCount(4, $keyboard[0]); // :00 :15 :30 :45
    }

    public function testStartPageCoversSixToEleven(): void
    {
        $keyboard = $this->extractKeyboard($this->buildStartPage());

        $this->assertSame('6:00', $keyboard[0][0]['text']);
        $this->assertSame('11:45', $keyboard[5][3]['text']);
    }

    public function testStartPageHasBothNavButtons(): void
    {
        $paginationRow = $this->navigationRow($this->extractKeyboard($this->buildStartPage()));

        $this->assertCount(2, $paginationRow);
        $this->assertStringContainsString('Prev', $paginationRow[0]['text']);
        $this->assertStringContainsString('Next', $paginationRow[1]['text']);
    }

    public function testTimeButtonCallbackCarriesNormalizedTime(): void
    {
        $keyboard = $this->extractKeyboard($this->buildStartPage());

        $callbackData = NewGameCallbackData::fromJson($keyboard[0][0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::PickTime, $callbackData->getAction());
        $this->assertSame('06:00', $callbackData->getTime());
    }

    public function testNavCallbackAdvancesTimePage(): void
    {
        $paginationRow = $this->navigationRow($this->extractKeyboard($this->buildStartPage()));

        $next = NewGameCallbackData::fromJson($paginationRow[1]['callback_data']);
        $this->assertSame(NewGameCallbackAction::ShowTimePage, $next->getAction());
        $this->assertSame(3, $next->getPage());
    }

    public function testFirstPageHasOnlyNext(): void
    {
        $keyboard = $this->extractKeyboard(
            $this->builder->build(new DateTimeImmutable('2099-12-31'), 1),
        );
        $paginationRow = $this->navigationRow($keyboard);

        $this->assertCount(1, $paginationRow);
        $this->assertStringContainsString('Next', $paginationRow[0]['text']);
    }

    public function testBackRowReturnsToDateStep(): void
    {
        $backRow = $this->backRow($this->extractKeyboard($this->buildStartPage()));

        $this->assertCount(1, $backRow);
        $this->assertStringContainsString('Back', $backRow[0]['text']);

        $back = NewGameCallbackData::fromJson($backRow[0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::ShowDatePage, $back->getAction());
        $this->assertSame(1, $back->getPage());
    }

    public function testFormShowsStepTwoWithDateFilledAndTimePlaceholder(): void
    {
        $text = $this->displayText($this->buildStartPage());

        $this->assertStringContainsString('Step 2 of 3', $text);
        $this->assertStringContainsString('31.12', $text); // date already picked
        $this->assertStringContainsString('pick a time below', $text);
    }

    private function buildStartPage(): TelegramMessage
    {
        return $this->builder->build(new DateTimeImmutable('2099-12-31'), NewGameTimePickerMessageBuilder::START_PAGE);
    }

    // Telegram shows the escaped MarkdownV2 with the backslashes stripped; assert against that.
    private function displayText(TelegramMessage $message): string
    {
        return str_replace('\\', '', $message->getText()->getMessageText());
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
