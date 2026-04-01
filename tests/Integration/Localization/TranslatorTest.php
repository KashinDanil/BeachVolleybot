<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Localization;

use BeachVolleybot\Localization\Translator;
use DanilKashin\Localization\Language;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        Translator::reset();
    }

    protected function tearDown(): void
    {
        Translator::reset();
    }

    public function testTranslateReturnsOriginalTextForDefaultLanguage(): void
    {
        $this->assertSame('Hello', Translator::translate('Hello'));
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
        Translator::setLanguage(Language::RU);

        $this->assertSame(
            'Некорректные данные запроса',
            Translator::translate('Invalid payload'),
        );
    }

    public function testTranslateFallsBackToEnglishForMissingKey(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'bvb_missing_');
        Translator::setInstance(new Translator(Language::RU, $tmpFile));

        try {
            $this->assertSame('Unknown key', Translator::translate('Unknown key'));
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testGetInstanceReturnsEnByDefault(): void
    {
        $this->assertTrue(Translator::getInstance()->isDefaultLanguage());
    }

    public function testSetInstanceReplacesSingleton(): void
    {
        $translator = new Translator(Language::RU);
        Translator::setInstance($translator);

        $this->assertSame($translator, Translator::getInstance());
    }

    public function testSetLanguageUpdatesSingleton(): void
    {
        Translator::setLanguage(Language::RU);

        $this->assertFalse(Translator::getInstance()->isDefaultLanguage());
    }

    public function testResetRestoresDefaultSingleton(): void
    {
        Translator::setLanguage(Language::RU);
        Translator::reset();

        $this->assertTrue(Translator::getInstance()->isDefaultLanguage());
    }

    public function testMissingTranslationIsWrittenToFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'bvb_missing_');
        Translator::setInstance(new Translator(Language::RU, $tmpFile));

        try {
            Translator::translate('This key does not exist');

            $written = json_decode(file_get_contents($tmpFile), true);
            $this->assertContains('This key does not exist', $written[Language::RU]);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testMissingTranslationIsNotTrackedTwice(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'bvb_missing_');
        Translator::setInstance(new Translator(Language::RU, $tmpFile));

        try {
            Translator::translate('Duplicate key');
            Translator::translate('Duplicate key');

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
        Translator::setInstance(new Translator(Language::EN, $tmpFile));

        try {
            Translator::translate('Any English text');

            $this->assertSame('', file_get_contents($tmpFile));
        } finally {
            @unlink($tmpFile);
        }
    }
}