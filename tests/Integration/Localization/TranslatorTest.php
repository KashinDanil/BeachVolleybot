<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Localization;

use BeachVolleybot\Localization\Translator;
use DanilKashin\Localization\Language;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    public function testTranslateReturnsOriginalTextForDefaultLanguage(): void
    {
        $translator = new Translator(Language::EN);

        $this->assertSame('Hello', $translator->translate('Hello'));
    }

    public function testIsDefaultLanguageReturnsTrueForEn(): void
    {
        $this->assertTrue((new Translator(Language::EN))->isDefaultLanguage());
    }

    public function testIsDefaultLanguageReturnsFalseForRu(): void
    {
        $this->assertFalse((new Translator(Language::RU))->isDefaultLanguage());
    }

    public function testTranslateReturnsTranslatedStringForRu(): void
    {
        $translator = new Translator(Language::RU);

        $this->assertSame(
            'Что-то пошло не так',
            $translator->translate('Something went wrong'),
        );
    }

    public function testTranslateFallsBackToEnglishForMissingKey(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'bvb_missing_');
        $translator = new Translator(Language::RU, $tmpFile);

        try {
            $this->assertSame('Unknown key', $translator->translate('Unknown key'));
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testMissingTranslationIsWrittenToFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'bvb_missing_');
        $translator = new Translator(Language::RU, $tmpFile);

        try {
            $translator->translate('This key does not exist');

            $written = json_decode(file_get_contents($tmpFile), true);
            $this->assertContains('This key does not exist', $written[Language::RU]);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testMissingTranslationIsNotTrackedTwice(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'bvb_missing_');
        $translator = new Translator(Language::RU, $tmpFile);

        try {
            $translator->translate('Duplicate key');
            $translator->translate('Duplicate key');

            $written     = json_decode(file_get_contents($tmpFile), true);
            $occurrences = array_count_values($written[Language::RU]);
            $this->assertSame(1, $occurrences['Duplicate key']);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testMissingTranslationIsNotTrackedForDefaultLanguage(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'bvb_missing_');
        $translator = new Translator(Language::EN, $tmpFile);

        try {
            $translator->translate('Any English text');

            $this->assertSame('', file_get_contents($tmpFile));
        } finally {
            @unlink($tmpFile);
        }
    }
}
