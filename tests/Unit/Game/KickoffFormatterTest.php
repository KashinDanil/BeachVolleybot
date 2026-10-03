<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game;

use BeachVolleybot\Game\KickoffFormatter;
use BeachVolleybot\Localization\Translator;
use DanilKashin\Localization\Language;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KickoffFormatterTest extends TestCase
{
    private const string KICKOFF = '2026-08-14 18:00';
    private const string NOW     = '2026-09-06 12:00';

    private string $missingTranslationsFile;

    protected function setUp(): void
    {
        $this->missingTranslationsFile = BASE_LOG_DIR . '/kickoff_formatter_missing_' . getmypid() . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->missingTranslationsFile);
    }

    public function testSpellsEachPartInEnglish(): void
    {
        $kickoffFormatter = $this->kickoffFormatter(self::KICKOFF, Language::EN);

        $this->assertSame('Fri', $kickoffFormatter->formatShortWeekday());
        $this->assertSame('on Friday', $kickoffFormatter->formatWeekdayWithPreposition());
        $this->assertSame('14 Aug', $kickoffFormatter->formatDayAndMonth());
        $this->assertSame('18:00', $kickoffFormatter->formatTime());
    }

    public function testOmitsTheYearWithinTheCurrentYear(): void
    {
        $this->assertSame('31 Dec', $this->kickoffFormatter('2026-12-31 23:30', Language::EN)->formatDayAndMonth());
    }

    public function testAddsTheYearForAnotherYear(): void
    {
        $this->assertSame('13 Aug 2027', $this->kickoffFormatter('2027-08-13 18:00', Language::EN)->formatDayAndMonth());
        $this->assertSame('21 Jun 2025', $this->kickoffFormatter('2025-06-21 09:30', Language::EN)->formatDayAndMonth());
    }

    public function testJudgesTheCurrentYearAtTheVenue(): void
    {
        $venueZone = new DateTimeZone('Europe/Madrid');
        $kickoffAt = new DateTimeImmutable('2027-01-01 10:00', $venueZone);
        // 23:30 UTC on 31 Dec is already 1 Jan 00:30 in Madrid.
        $now = new DateTimeImmutable('2026-12-31 23:30', new DateTimeZone('UTC'));

        $this->assertSame('1 Jan', new KickoffFormatter($kickoffAt, $this->translator(Language::EN), $now)->formatDayAndMonth());
    }

    /** @return iterable<string, array{string, string, string, string}> date, English, Russian, Spanish */
    public static function everyWeekday(): iterable
    {
        yield 'Monday' => ['2026-08-10', 'on Monday', 'в понедельник', 'el lunes'];
        yield 'Tuesday' => ['2026-08-11', 'on Tuesday', 'во вторник', 'el martes'];
        yield 'Wednesday' => ['2026-08-12', 'on Wednesday', 'в среду', 'el miércoles'];
        yield 'Thursday' => ['2026-08-13', 'on Thursday', 'в четверг', 'el jueves'];
        yield 'Friday' => ['2026-08-14', 'on Friday', 'в пятницу', 'el viernes'];
        yield 'Saturday' => ['2026-08-15', 'on Saturday', 'в субботу', 'el sábado'];
        yield 'Sunday' => ['2026-08-16', 'on Sunday', 'в воскресенье', 'el domingo'];
    }

    #[DataProvider('everyWeekday')]
    public function testSpellsEveryWeekdayWithItsPrepositionInEveryLanguage(
        string $date,
        string $english,
        string $russian,
        string $spanish,
    ): void {
        $this->assertSame($english, $this->kickoffFormatter("$date 18:00", Language::EN)->formatWeekdayWithPreposition());
        $this->assertSame($russian, $this->kickoffFormatter("$date 18:00", Language::RU)->formatWeekdayWithPreposition());
        $this->assertSame($spanish, $this->kickoffFormatter("$date 18:00", Language::ES)->formatWeekdayWithPreposition());
    }

    public function testSpellsTheShortWeekdayAndMonthInEveryLanguage(): void
    {
        $this->assertSame('пт', $this->kickoffFormatter(self::KICKOFF, Language::RU)->formatShortWeekday());
        $this->assertSame('14 авг', $this->kickoffFormatter(self::KICKOFF, Language::RU)->formatDayAndMonth());
        $this->assertSame('vie', $this->kickoffFormatter(self::KICKOFF, Language::ES)->formatShortWeekday());
        $this->assertSame('14 ago', $this->kickoffFormatter(self::KICKOFF, Language::ES)->formatDayAndMonth());
    }

    public function testFallsBackToEnglishForALanguageTheBotDoesNotSpeak(): void
    {
        $kickoffFormatter = $this->kickoffFormatter(self::KICKOFF, Language::DE);

        $this->assertSame('on Friday', $kickoffFormatter->formatWeekdayWithPreposition());
        $this->assertSame('14 Aug', $kickoffFormatter->formatDayAndMonth());
    }

    public function testPadsTheTimeToTwoDigits(): void
    {
        $this->assertSame('09:05', $this->kickoffFormatter('2026-08-14 09:05', Language::EN)->formatTime());
        $this->assertSame('00:00', $this->kickoffFormatter('2026-08-14 00:00', Language::EN)->formatTime());
    }

    public function testDoesNotPadTheDayOfTheMonth(): void
    {
        $this->assertSame('1 Aug', $this->kickoffFormatter('2026-08-01 18:00', Language::EN)->formatDayAndMonth());
    }

    public function testShowsTheYearOnceTheNewYearHasBegunAtTheVenue(): void
    {
        $venueZone = new DateTimeZone('Europe/Madrid');
        $kickoffAt = new DateTimeImmutable('2026-12-31 20:00', $venueZone);
        // 23:30 UTC on 31 Dec is already 1 Jan 2027 00:30 in Madrid.
        $now = new DateTimeImmutable('2026-12-31 23:30', new DateTimeZone('UTC'));

        $this->assertSame('31 Dec 2026', new KickoffFormatter($kickoffAt, $this->translator(Language::EN), $now)->formatDayAndMonth());
    }

    public function testComparesAgainstTheCurrentYearByDefault(): void
    {
        $kickoffAt = new DateTimeImmutable('today 18:00');

        $this->assertStringNotContainsString(
            $kickoffAt->format('Y'),
            new KickoffFormatter($kickoffAt, $this->translator(Language::EN))->formatDayAndMonth(),
        );
    }

    private function kickoffFormatter(string $kickoffAt, string $language): KickoffFormatter
    {
        return new KickoffFormatter(
            new DateTimeImmutable($kickoffAt),
            $this->translator($language),
            new DateTimeImmutable(self::NOW),
        );
    }

    private function translator(string $language): Translator
    {
        return new Translator($language, $this->missingTranslationsFile);
    }
}
