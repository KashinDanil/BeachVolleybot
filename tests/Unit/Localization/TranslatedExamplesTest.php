<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Localization;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Common\GameDateResolver;
use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\HelpMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\InlineQueryError;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The bot tells users what to write. Every locale's version of that advice must parse, and mean
 * the same kickoff as the English it was translated from.
 */
final class TranslatedExamplesTest extends TestCase
{
    private const array WITH_KICKOFF = [
        InlineQueryError::DATE_AND_TIME_NOT_FOUND_DESCRIPTION,
        HelpMessageBuilder::EXAMPLE_TEMPLATE,
    ];

    private const array WITH_DATE = [InlineQueryError::DATE_NOT_FOUND_DESCRIPTION];

    private const array WITH_TIME = [InlineQueryError::TIME_NOT_FOUND_DESCRIPTION];

    private const string LOCALIZATION_DIR = __DIR__ . '/../../../localization';

    private const string MISSING_TRANSLATIONS_FILE = 'missing.json';

    private const string NOW = '2026-01-15';

    private string $missingFile;

    protected function setUp(): void
    {
        $this->missingFile = sys_get_temp_dir() . '/bvb_translated_examples_missing.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->missingFile);
    }

    public function testEveryLocaleTranslatesTheAdviceItGives(): void
    {
        $keys = [...self::WITH_KICKOFF, ...self::WITH_DATE, ...self::WITH_TIME];

        foreach (self::translationFiles() as $locale => $translations) {
            $missing = array_diff($keys, array_keys($translations));

            $this->assertEmpty($missing, "$locale has no translation for: " . implode(' | ', $missing));
        }
    }

    public function testEverySuggestedTitleMeansTheSameKickoffInEveryLocale(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $kickoff = static fn(string $text): ?string => GameDateTimeResolver::resolve($text, $now)?->format('Y-m-d H:i');

        $this->assertSameInEveryLocale(self::WITH_KICKOFF, $kickoff, 'kickoff');
    }

    public function testEverySuggestedDateMeansTheSameDayInEveryLocale(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $date = static fn(string $text): ?string => GameDateResolver::resolve($text, $now)?->format('Y-m-d');

        $this->assertSameInEveryLocale(self::WITH_DATE, $date, 'date');
    }

    public function testEverySuggestedTimeMeansTheSameHourInEveryLocale(): void
    {
        $this->assertSameInEveryLocale(self::WITH_TIME, TimeExtractor::extract(...), 'time');
    }

    /** @param list<string> $keys */
    private function assertSameInEveryLocale(array $keys, callable $read, string $what): void
    {
        foreach ($keys as $key) {
            $expected = $read($key);
            $this->assertNotNull($expected, "The English example '$key' carries no $what");

            foreach ($this->translations($key) as $locale => $example) {
                $this->assertSame(
                    $expected,
                    $read($example),
                    "$locale suggests '$example', whose $what is not the $what of '$key'",
                );
            }
        }
    }

    /** @return array<string, array<string, string>> locale => translations */
    private static function translationFiles(): array
    {
        $files = [];

        foreach (glob(self::LOCALIZATION_DIR . '/*.json') ?: [] as $path) {
            if (self::MISSING_TRANSLATIONS_FILE === basename($path)) {
                continue;
            }

            $translations = json_decode(file_get_contents($path), true);
            self::assertIsArray($translations, basename($path) . ' is not valid JSON');

            $files[basename($path, '.json')] = $translations;
        }

        return $files;
    }

    /** @return array<string, string> locale => translated example */
    private function translations(string $key): array
    {
        $translations = [];

        foreach (Translator::supportedLanguages() as $language) {
            $translations[$language] = new Translator($language, $this->missingFile)->translate($key);
        }

        return $translations;
    }
}
