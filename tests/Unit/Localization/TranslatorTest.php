<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Localization;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use DanilKashin\Localization\Language;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    public function testFromLanguageCodeNormalizesTheRawTelegramCode(): void
    {
        $this->assertSame(Language::RU, Translator::fromLanguageCode('ru-RU')->language());
    }

    public function testFromLanguageCodeFallsBackToEnglishWhenUnknown(): void
    {
        $this->assertSame(Language::EN, Translator::fromLanguageCode(null)->language());
    }

    public function testFromUserUsesTheUsersLanguageCode(): void
    {
        $user = new TelegramUser(id: 200, firstName: 'Danil', languageCode: 'es');

        $this->assertSame(Language::ES, Translator::fromUser($user)->language());
    }
}
