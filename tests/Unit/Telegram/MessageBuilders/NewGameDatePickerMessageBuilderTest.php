<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\NewGameDatePickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class NewGameDatePickerMessageBuilderTest extends TestCase
{
    private const string TODAY = '2099-12-31';

    private NewGameDatePickerMessageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new NewGameDatePickerMessageBuilder(new Translator(), new DateTimeImmutable(self::TODAY));
    }

    public function testFirstPageHasSevenDatesThenNextOnly(): void
    {
        $keyboard = $this->extractKeyboard($this->builder->build(1));

        $this->assertCount(8, $keyboard); // 7 date rows + 1 nav row
        $paginationRow = end($keyboard);
        $this->assertCount(1, $paginationRow);
        $this->assertStringContainsString('Next', $paginationRow[0]['text']);
    }

    public function testFirstDateIsTodayWithoutYear(): void
    {
        $keyboard = $this->extractKeyboard($this->builder->build(1));

        $this->assertStringContainsString('31.12', $keyboard[0][0]['text']);
        $this->assertStringNotContainsString('2099', $keyboard[0][0]['text']);
    }

    public function testDateButtonCallbackCarriesFullIsoDate(): void
    {
        $keyboard = $this->extractKeyboard($this->builder->build(1));

        $callbackData = NewGameCallbackData::fromJson($keyboard[0][0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::PickDate, $callbackData->getAction());
        $this->assertSame(self::TODAY, $callbackData->getDate());
    }

    public function testNextButtonAdvancesDatePage(): void
    {
        $keyboard = $this->extractKeyboard($this->builder->build(1));
        $paginationRow = end($keyboard);

        $callbackData = NewGameCallbackData::fromJson($paginationRow[0]['callback_data']);
        $this->assertSame(NewGameCallbackAction::ShowDatePage, $callbackData->getAction());
        $this->assertSame(2, $callbackData->getPage());
    }

    public function testLastPageHasOnlyPrev(): void
    {
        $keyboard = $this->extractKeyboard($this->builder->build(4));
        $paginationRow = end($keyboard);

        $this->assertCount(1, $paginationRow);
        $this->assertStringContainsString('Prev', $paginationRow[0]['text']);
    }

    public function testWeekendDatesGetPrimaryStyleAndWeekdaysDoNot(): void
    {
        // Page 1 window from 2099-12-31: Thu, Fri, Sat, Sun, Mon, Tue, Wed.
        $keyboard = $this->extractKeyboard($this->builder->build(1));

        $this->assertArrayNotHasKey('style', $keyboard[0][0]); // Thursday
        $this->assertArrayNotHasKey('style', $keyboard[1][0]); // Friday
        $this->assertSame('primary', $keyboard[2][0]['style']); // Saturday
        $this->assertSame('primary', $keyboard[3][0]['style']); // Sunday
        $this->assertArrayNotHasKey('style', $keyboard[4][0]); // Monday
    }

    public function testFormShowsStepOneWithDatePlaceholder(): void
    {
        $text = $this->builder->build(1)->getText()->getMessageText();

        $this->assertStringContainsString('Step 1 of 3', $text);
        $this->assertStringContainsString('pick a date below', $text);
        $this->assertStringContainsString('—', $text); // empty time + location
    }

    private function extractKeyboard(TelegramMessage $message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }
}
