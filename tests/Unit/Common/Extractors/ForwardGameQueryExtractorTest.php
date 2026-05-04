<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common\Extractors;

use BeachVolleybot\Common\Extractors\ForwardGameQueryExtractor;
use BeachVolleybot\Localization\Translator;
use DanilKashin\Localization\Language;
use PHPUnit\Framework\TestCase;

final class ForwardGameQueryExtractorTest extends TestCase
{
    private ?string $missingTranslationsFile = null;

    protected function tearDown(): void
    {
        if (null !== $this->missingTranslationsFile) {
            @unlink($this->missingTranslationsFile);
            $this->missingTranslationsFile = null;
        }
    }

    public function testExtractsGameIdFromEnglishQuery(): void
    {
        $this->assertSame(
            123,
            ForwardGameQueryExtractor::extract('Forward game 123', $this->translator(Language::EN)),
        );
    }

    public function testExtractsGameIdFromRussianQuery(): void
    {
        $this->assertSame(
            7,
            ForwardGameQueryExtractor::extract('Переслать игру 7', $this->translator(Language::RU)),
        );
    }

    public function testExtractsGameIdFromSpanishQuery(): void
    {
        $this->assertSame(
            42,
            ForwardGameQueryExtractor::extract('Reenviar partido 42', $this->translator(Language::ES)),
        );
    }

    public function testToleratesMultipleSpacesBetweenPrefixAndId(): void
    {
        $this->assertSame(
            9,
            ForwardGameQueryExtractor::extract('Forward game   9', $this->translator(Language::EN)),
        );
    }

    public function testIsCaseInsensitiveForEnglishPrefix(): void
    {
        $this->assertSame(
            123,
            ForwardGameQueryExtractor::extract('forward GAME 123', $this->translator(Language::EN)),
        );
    }

    public function testIsCaseInsensitiveForRussianPrefix(): void
    {
        $this->assertSame(
            7,
            ForwardGameQueryExtractor::extract('пЕрЕсЛаТь ИгРу 7', $this->translator(Language::RU)),
        );
    }

    public function testReturnsNullWhenIdIsMissing(): void
    {
        $this->assertNull(
            ForwardGameQueryExtractor::extract('Forward game', $this->translator(Language::EN)),
        );
    }

    public function testReturnsNullWhenIdIsNotNumeric(): void
    {
        $this->assertNull(
            ForwardGameQueryExtractor::extract('Forward game abc', $this->translator(Language::EN)),
        );
    }

    public function testReturnsNullWhenIdIsZero(): void
    {
        $this->assertNull(
            ForwardGameQueryExtractor::extract('Forward game 0', $this->translator(Language::EN)),
        );
    }

    public function testReturnsNullForUnrelatedQuery(): void
    {
        $this->assertNull(
            ForwardGameQueryExtractor::extract('Saturday 18:00', $this->translator(Language::EN)),
        );
    }

    public function testReturnsNullForEmptyQuery(): void
    {
        $this->assertNull(
            ForwardGameQueryExtractor::extract('', $this->translator(Language::EN)),
        );
    }

    public function testReturnsNullWhenLocalePrefixDoesNotMatchUserLocale(): void
    {
        $this->assertNull(
            ForwardGameQueryExtractor::extract('Переслать игру 7', $this->translator(Language::EN)),
        );
    }

    public function testReturnsNullWhenLeadingWhitespacePresent(): void
    {
        $this->assertNull(
            ForwardGameQueryExtractor::extract('  Forward game 5', $this->translator(Language::EN)),
        );
    }

    public function testReturnsNullWhenIdHasTrailingNonDigit(): void
    {
        $this->assertNull(
            ForwardGameQueryExtractor::extract('Forward game 12a', $this->translator(Language::EN)),
        );
    }

    private function translator(string $language): Translator
    {
        $this->missingTranslationsFile ??= tempnam(sys_get_temp_dir(), 'bvb_missing_');

        return new Translator($language, $this->missingTranslationsFile);
    }
}
