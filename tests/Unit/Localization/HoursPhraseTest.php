<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Localization;

use BeachVolleybot\Localization\HoursPhrase;
use BeachVolleybot\Localization\Translator;
use DanilKashin\Localization\Language;
use PHPUnit\Framework\TestCase;

final class HoursPhraseTest extends TestCase
{
    public function testEnglishSwitchesToTheSingularAtOne(): void
    {
        $translator = new Translator();

        $this->assertSame('1 hour', new HoursPhrase(1, $translator)->text());
        $this->assertSame('12 hours', new HoursPhrase(12, $translator)->text());
    }

    public function testSpanishSwitchesToTheSingularAtOne(): void
    {
        $translator = self::translator(Language::ES);

        $this->assertSame('1 hora', new HoursPhrase(1, $translator)->text());
        $this->assertSame('12 horas', new HoursPhrase(12, $translator)->text());
    }

    public function testRussianSwitchesNounFormByLastDigit(): void
    {
        $translator = self::translator(Language::RU);

        $this->assertSame('1 час', new HoursPhrase(1, $translator)->text());
        $this->assertSame('2 часа', new HoursPhrase(2, $translator)->text());
        $this->assertSame('12 часов', new HoursPhrase(12, $translator)->text());
        $this->assertSame('21 час', new HoursPhrase(21, $translator)->text());
    }

    private static function translator(string $language): Translator
    {
        return new Translator($language, tempnam(sys_get_temp_dir(), 'bvb_missing_'));
    }
}
