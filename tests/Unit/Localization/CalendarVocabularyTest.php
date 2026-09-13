<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Localization;

use BeachVolleybot\Common\Extractors\DateExtractor;
use BeachVolleybot\Common\Extractors\DayOfWeekExtractor;
use BeachVolleybot\Localization\CalendarVocabulary;
use BeachVolleybot\Localization\Translator;
use DanilKashin\Localization\Language;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CalendarVocabularyTest extends TestCase
{
    private const string LOCALIZATION_DIR = __DIR__ . '/../../../localization';

    private const string MISSING_TRANSLATIONS_FILE = 'missing.json';

    /** The keys GameLabel translates to print a kickoff. */
    private const array WEEKDAY_KEYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    private const array MONTH_KEYS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /** The keys the /new_game wizard translates to print a date. */
    private const array FULL_WEEKDAY_KEYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function testTheBotReadsExactlyTheLanguagesItWrites(): void
    {
        $written = Translator::supportedLanguages();
        $read = CalendarVocabulary::languages();

        sort($written);
        sort($read);

        $this->assertSame($written, $read, 'A language has a localization file but no vocabulary, or the other way round');
    }

    public function testWeekdaysAndMonthsCoverTheSameLanguages(): void
    {
        $vocabulary = self::vocabulary();
        $weekdays = array_keys($vocabulary['WEEKDAYS']);
        $months = array_keys($vocabulary['MONTHS']);

        $this->assertEmpty(array_diff($weekdays, $months), 'Named weekdays but no months: ' . implode(', ', array_diff($weekdays, $months)));
        $this->assertEmpty(array_diff($months, $weekdays), 'Named months but no weekdays: ' . implode(', ', array_diff($months, $weekdays)));
    }

    public function testOrdinalsAndPrepositionsBelongToDeclaredLanguages(): void
    {
        $vocabulary = self::vocabulary();

        foreach (['ORDINALS', 'PREPOSITIONS'] as $kind) {
            $unknown = array_diff(array_keys($vocabulary[$kind]), CalendarVocabulary::languages());

            $this->assertEmpty($unknown, "$kind covers languages with no weekday or month names: " . implode(', ', $unknown));
        }
    }

    public function testEveryLanguageNamesEveryWeekdayAndMonth(): void
    {
        $vocabulary = self::vocabulary();

        foreach ($vocabulary['WEEKDAYS'] as $language => $names) {
            $this->assertSame(range(1, 7), self::numbersNamed($names), "$language does not name every weekday");
        }

        foreach ($vocabulary['MONTHS'] as $language => $names) {
            $this->assertSame(range(1, 12), self::numbersNamed($names), "$language does not name every month");
        }
    }

    public function testEveryOrdinalAndPrepositionIsRead(): void
    {
        $now = new DateTimeImmutable('2026-01-15');

        foreach (CalendarVocabulary::ordinals() as $ordinal) {
            $title = "Game 11$ordinal April";

            $this->assertSame("11$ordinal April", DateExtractor::extract($title), "'$ordinal' is declared but not read");
            $this->assertSame(4, (int) DateExtractor::resolveDate($title, $now)?->format('n'), "'$ordinal' breaks the month");
        }

        foreach (CalendarVocabulary::prepositions() as $preposition) {
            $title = "Game 11 $preposition April";

            $this->assertSame("11 $preposition April", DateExtractor::extract($title), "'$preposition' is declared but not read");
            $this->assertSame(4, (int) DateExtractor::resolveDate($title, $now)?->format('n'), "'$preposition' breaks the month");
        }
    }

    public function testEveryWeekdayIsExtractedAsTheDayItDeclares(): void
    {
        $monday = new DateTimeImmutable('2026-01-05');

        foreach (CalendarVocabulary::weekdays() as $name => $isoDay) {
            $title = "Game $name 18:00";

            $this->assertSame($name, DayOfWeekExtractor::extract($title), "'$name' is declared but not extracted");
            $this->assertSame(
                $isoDay,
                (int) DayOfWeekExtractor::resolveDate($title, $monday)?->format('N'),
                "'$name' is declared as ISO day $isoDay but does not resolve to one",
            );
        }
    }

    public function testEveryMonthIsExtractedAsTheMonthItDeclares(): void
    {
        $now = new DateTimeImmutable('2026-01-15');

        foreach (CalendarVocabulary::months() as $name => $month) {
            $title = "Game 11 $name";

            $this->assertSame("11 $name", DateExtractor::extract($title), "'$name' is declared but not extracted");
            $this->assertSame(
                $month,
                (int) DateExtractor::resolveDate($title, $now)?->format('n'),
                "'$name' is declared as month $month but does not resolve to one",
            );
        }
    }

    public function testNoNameMeansTwoDifferentThings(): void
    {
        foreach (['WEEKDAYS', 'MONTHS'] as $kind) {
            $byLanguage = self::vocabulary()[$kind];
            $seen = [];

            foreach ($byLanguage as $language => $names) {
                foreach ($names as $name => $value) {
                    $existing = $seen[$name] ?? $value;

                    $this->assertSame($existing, $value, "$kind: '$name' is $existing elsewhere but $value in $language");
                    $seen[$name] = $value;
                }
            }
        }

        $ambiguous = array_intersect_key(CalendarVocabulary::weekdays(), CalendarVocabulary::months());

        $this->assertEmpty($ambiguous, 'Read as both a day and a month: ' . implode(', ', array_keys($ambiguous)));
    }

    /** languageByName() picks the first-declared language on a collision; this keeps that tie-break from ever firing. */
    public function testNoNameIsClaimedByTwoLanguages(): void
    {
        foreach (['WEEKDAYS', 'MONTHS'] as $kind) {
            $byLanguage = self::vocabulary()[$kind];
            $languageByName = [];

            foreach ($byLanguage as $language => $names) {
                foreach (array_keys($names) as $name) {
                    $existing = $languageByName[$name] ?? $language;

                    $this->assertSame($existing, $language, "$kind: '$name' is named by both $existing and $language");
                    $languageByName[$name] = $language;
                }
            }
        }
    }

    public function testEveryLocaleNamesEveryWeekdayAndMonth(): void
    {
        $required = [...self::WEEKDAY_KEYS, ...self::MONTH_KEYS];

        foreach (self::printedNames() as $locale => $names) {
            $missing = array_diff($required, array_keys($names));

            $this->assertEmpty($missing, "$locale has no name for: " . implode(', ', $missing));
        }
    }

    public function testEveryLocaleSpellsEveryWeekdayOutInFull(): void
    {
        foreach (self::printedNames() as $locale => $names) {
            $missing = array_diff(self::FULL_WEEKDAY_KEYS, array_keys($names));

            $this->assertEmpty($missing, "$locale has no full name for: " . implode(', ', $missing));
        }
    }

    public function testEveryFullWeekdayItWritesItCanReadBack(): void
    {
        // The wizard prints these into the game title, which is re-read later for the kickoff.
        $monday = new DateTimeImmutable('2026-01-05');

        foreach (self::printedNames() as $locale => $names) {
            foreach (self::FULL_WEEKDAY_KEYS as $index => $key) {
                $printed = $names[$key];

                $this->assertSame(
                    $index + 1,
                    (int) DayOfWeekExtractor::resolveDate("Game $printed 18:00", $monday)?->format('N'),
                    "$locale prints '$printed' for $key but cannot read it back",
                );
            }
        }
    }

    /**
     * @param array<string, int> $names
     *
     * @return list<int>
     */
    private static function numbersNamed(array $names): array
    {
        $numbers = array_values(array_unique(array_values($names)));
        sort($numbers);

        return $numbers;
    }

    /** @return array<string, array<string, array<array-key, int|string>>> kind => language => entries */
    private static function vocabulary(): array
    {
        $constants = new ReflectionClass(CalendarVocabulary::class)->getConstants();

        return array_intersect_key($constants, array_flip(['WEEKDAYS', 'MONTHS', 'ORDINALS', 'PREPOSITIONS']));
    }

    /**
     * What GameLabel prints per locale. English has no file — Translator hands back the key itself.
     *
     * @return array<string, array<string, string>> locale => key => printed name
     */
    private static function printedNames(): array
    {
        $keys = [...self::WEEKDAY_KEYS, ...self::MONTH_KEYS, ...self::FULL_WEEKDAY_KEYS];
        $printed = [Language::EN => array_combine($keys, $keys)];

        foreach (glob(self::LOCALIZATION_DIR . '/*.json') ?: [] as $path) {
            if (self::MISSING_TRANSLATIONS_FILE === basename($path)) {
                continue;
            }

            $names = json_decode(file_get_contents($path), true);
            self::assertIsArray($names, basename($path) . ' is not valid JSON');

            $printed[basename($path, '.json')] = $names;
        }

        return $printed;
    }
}
