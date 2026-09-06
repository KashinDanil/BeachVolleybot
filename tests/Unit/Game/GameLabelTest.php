<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game;

use BeachVolleybot\Game\GameLabel;
use BeachVolleybot\Localization\Translator;
use DanilKashin\Localization\Language;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameLabelTest extends TestCase
{
    private const string KICKOFF = '2026-08-14 18:00';
    private const string NOW     = '2026-09-06 12:00';

    public function testShowsGameIdWeekdayDateAndTime(): void
    {
        $this->assertSame('#42 · Fri, 14 Aug 18:00', $this->format(42, self::KICKOFF, Language::EN));
    }

    public function testSpellsTheKickoffInRussian(): void
    {
        $this->assertSame('#42 · пт, 14 авг 18:00', $this->format(42, self::KICKOFF, Language::RU));
    }

    public function testSpellsTheKickoffInSpanish(): void
    {
        $this->assertSame('#42 · vie, 14 ago 18:00', $this->format(42, self::KICKOFF, Language::ES));
    }

    public function testFallsBackToEnglishForALanguageTheBotDoesNotSpeak(): void
    {
        $this->assertSame('#42 · Fri, 14 Aug 18:00', $this->format(42, self::KICKOFF, Language::DE));
    }

    public function testOmitsTheYearWithinTheCurrentYear(): void
    {
        $this->assertSame('#7 · Thu, 31 Dec 23:30', $this->format(7, '2026-12-31 23:30', Language::EN));
    }

    public function testAddsTheYearForAGameFromAnEarlierYear(): void
    {
        $this->assertSame('#7 · Sat, 21 Jun 2025 09:30', $this->format(7, '2025-06-21 09:30', Language::EN));
    }

    public function testAddsTheYearForAGameInALaterYear(): void
    {
        $this->assertSame('#7 · Fri, 1 Jan 2027 09:30', $this->format(7, '2027-01-01 09:30', Language::EN));
    }

    public function testPadsTheHourToTwoDigits(): void
    {
        $this->assertSame('#3 · Fri, 14 Aug 09:05', $this->format(3, '2026-08-14 09:05', Language::EN));
    }

    private function format(int $gameId, string $kickoffAt, string $language): string
    {
        return GameLabel::format(
            $gameId,
            new DateTimeImmutable($kickoffAt),
            new Translator($language, tempnam(sys_get_temp_dir(), 'bvb_missing_')),
            new DateTimeImmutable(self::NOW),
        );
    }
}
