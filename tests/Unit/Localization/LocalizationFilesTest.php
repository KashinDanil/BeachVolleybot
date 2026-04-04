<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Localization;

use PHPUnit\Framework\TestCase;

final class LocalizationFilesTest extends TestCase
{
    private const string LOCALIZATION_DIR = __DIR__ . '/../../../localization';

    /** @return array<string, array<string, string>> filename => translations */
    private static function loadAllFiles(): array
    {
        $files = glob(self::LOCALIZATION_DIR . '/*.json');
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

    public function testAllFilesContainValidJson(): void
    {
        foreach (glob(self::LOCALIZATION_DIR . '/*.json') as $path) {
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
}