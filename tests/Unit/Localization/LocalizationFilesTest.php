<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Localization;

use PHPUnit\Framework\TestCase;

final class LocalizationFilesTest extends TestCase
{
    private const string LOCALIZATION_DIR = __DIR__ . '/../../../localization';

    /** The bot writes this one itself; it is a report, not a translation file. */
    private const string MISSING_TRANSLATIONS_FILE = 'missing.json';

    /** @return list<string> */
    private static function translationFiles(): array
    {
        return array_values(array_filter(
            glob(self::LOCALIZATION_DIR . '/*.json'),
            static fn(string $path): bool => self::MISSING_TRANSLATIONS_FILE !== basename($path),
        ));
    }

    /** @return array<string, array<string, string>> filename => translations */
    private static function loadAllFiles(): array
    {
        $files = self::translationFiles();
        self::assertNotEmpty($files, 'No localization files found');

        $all = [];

        foreach ($files as $path) {
            $filename = basename($path);
            $content = file_get_contents($path);
            $decoded = json_decode($content, true);

            self::assertIsArray($decoded, "File $filename contains invalid JSON");

            $all[$filename] = $decoded;
        }

        return $all;
    }

    /** Translator writes here by default, so a test that skipped passing its own file left this behind. */
    public function testNoMissingTranslationsFileIsLeftInTheRepository(): void
    {
        $this->assertFileDoesNotExist(
            self::LOCALIZATION_DIR . '/' . self::MISSING_TRANSLATIONS_FILE,
            'A test wrote this. Pass a temporary file as the Translator\'s second argument.',
        );
    }

    public function testAllFilesContainValidJson(): void
    {
        foreach (self::translationFiles() as $path) {
            $filename = basename($path);
            $content = file_get_contents($path);

            json_decode($content, true);
            $this->assertSame(JSON_ERROR_NONE, json_last_error(), "File $filename contains invalid JSON: " . json_last_error_msg());
        }
    }

    public function testAllFilesHaveTheSameKeys(): void
    {
        $all = self::loadAllFiles();
        $filenames = array_keys($all);

        $referenceFile = $filenames[0];
        $referenceKeys = array_keys($all[$referenceFile]);
        sort($referenceKeys);

        foreach (array_slice($filenames, 1) as $filename) {
            $keys = array_keys($all[$filename]);
            sort($keys);

            $missingInFile = array_diff($referenceKeys, $keys);
            $extraInFile = array_diff($keys, $referenceKeys);

            $this->assertEmpty(
                $missingInFile,
                "$filename is missing keys present in $referenceFile: " . implode(', ', $missingInFile),
            );

            $this->assertEmpty(
                $extraInFile,
                "$filename has extra keys not in $referenceFile: " . implode(', ', $extraInFile),
            );
        }
    }

    public function testNoEmptyTranslations(): void
    {
        $all = self::loadAllFiles();

        foreach ($all as $filename => $translations) {
            foreach ($translations as $key => $value) {
                $this->assertIsString($value, "File $filename: key '$key' must be a string");
                $this->assertNotSame('', trim($value), "File $filename: key '$key' has an empty translation");
            }
        }
    }

    public function testNoDuplicateValues(): void
    {
        $all = self::loadAllFiles();

        foreach ($all as $filename => $translations) {
            $seen = [];

            foreach ($translations as $key => $value) {
                $existingKey = $seen[$value] ?? '';
                $this->assertArrayNotHasKey(
                    $value,
                    $seen,
                    "File $filename: keys '$existingKey' and '$key' have the same translation '$value'",
                );
                $seen[$value] = $key;
            }
        }
    }

    /**
     * A translation that drops a placeholder throws ArgumentCountError at render time, not just
     * at read time. Reordering via positional specifiers (`%1$s`, `%2$s`) is legitimate and must
     * not be flagged — so this compares the number of sprintf *arguments* each string requires,
     * not a literal count of `%s`/`%d` occurrences.
     */
    public function testSprintfPlaceholdersMatchTheEnglishKeyInEveryLocale(): void
    {
        $all = self::loadAllFiles();

        foreach ($all as $filename => $translations) {
            foreach ($translations as $key => $value) {
                $this->assertSame(
                    self::requiredArgumentCount($key),
                    self::requiredArgumentCount($value),
                    "File $filename: '$value' requires a different number of sprintf arguments than '$key'",
                );
            }
        }
    }

    /**
     * How many positional arguments a sprintf() call against $text must supply, so a translation
     * that reorders arguments (`%1$s`), or adds flags/width/precision (`%.2f`, `%05d`), still
     * compares correctly against the English key.
     */
    private static function requiredArgumentCount(string $text): int
    {
        preg_match_all("/%(?:(\d+)\\\$)?(?:[-+ 0]|'.)*\d*(?:\.\d+)?([%bcdeEufFgGosxX])/", $text, $matches, PREG_SET_ORDER);

        $sequentialCount = 0;
        $maxPositionalIndex = 0;

        foreach ($matches as [, $positionalIndex, $specifier]) {
            if ('%' === $specifier) {
                continue; // %% is a literal percent sign — it consumes no argument
            }

            if ('' === $positionalIndex) {
                $sequentialCount++;
            } else {
                $maxPositionalIndex = max($maxPositionalIndex, (int) $positionalIndex);
            }
        }

        return max($sequentialCount, $maxPositionalIndex);
    }
}
