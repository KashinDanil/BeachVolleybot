<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Localization;

use BeachVolleybot\Localization\TitleLanguageResolver;
use DanilKashin\Localization\Language;
use PHPUnit\Framework\TestCase;

final class TitleLanguageResolverTest extends TestCase
{
    public function testWeekdayTitleResolvesToItsLanguage(): void
    {
        $this->assertSame(Language::RU, TitleLanguageResolver::resolve('Суббота 18:00'));
    }

    public function testMonthNameTitleResolvesToItsLanguage(): void
    {
        $this->assertSame(Language::ES, TitleLanguageResolver::resolve('Bogatell 11 de abril 18:00'));
    }

    public function testNumericDateFallsBackToEnglish(): void
    {
        $this->assertSame(Language::EN, TitleLanguageResolver::resolve('Beach 31.12.2099 18:00'));
    }

    public function testNoDateFallsBackToEnglish(): void
    {
        $this->assertSame(Language::EN, TitleLanguageResolver::resolve('Beach Game'));
    }

    public function testMixedLanguageTitleResolvesToWhicheverWeekdayAppearsFirst(): void
    {
        $this->assertSame(Language::EN, TitleLanguageResolver::resolve('Saturday Суббота 18:00'));
        $this->assertSame(Language::RU, TitleLanguageResolver::resolve('Суббота Saturday 18:00'));
    }

    /** Weekday is checked before month regardless of where each word sits in the title. */
    public function testWeekdayWinsOverMonthRegardlessOfPosition(): void
    {
        $this->assertSame(Language::EN, TitleLanguageResolver::resolve('11 de abril Saturday 18:00'));
    }
}
