<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\Extractors;

use BeachVolleybot\Common\ParsedDate;
use BeachVolleybot\Localization\CalendarVocabulary;
use DateTimeImmutable;

final class DateExtractor implements ExtractorInterface
{
    private const string NUMBERS_SUBPATTERN = '\d{1,2}\.\d{2}(?:\.\d{2,4})?';
    public const string NUMBERS_PATTERN = '/\b' . self::NUMBERS_SUBPATTERN . '\b/';

    private const string NUMERIC_PARSE_PATTERN = '/^(\d{1,2})\.(\d{2})(?:\.(\d{2,4}))?$/';
    private const string DAY_NUMBER_PATTERN = '/\d{1,2}/';

    private static ?string $pattern = null;

    private static ?string $textPattern = null;

    private static ?string $nonMonthStripPattern = null;

    public static function pattern(): string
    {
        return self::$pattern ??= '/(*UCP)\b(?:' . self::NUMBERS_SUBPATTERN . '|' . self::textSubpattern() . ')\b/iu';
    }

    public static function textPattern(): string
    {
        return self::$textPattern ??= '/(*UCP)\b(?:' . self::textSubpattern() . ')\b/iu';
    }

    public static function extract(string $text): ?string
    {
        if (1 !== preg_match(self::pattern(), $text, $matches)) {
            return null;
        }

        return $matches[0];
    }

    /** The month name a text date in the title carries, or null when it carries none. */
    public static function extractMonthName(string $text): ?string
    {
        if (1 !== preg_match(self::textPattern(), $text, $matches)) {
            return null;
        }

        return self::toMonthName($matches[0]);
    }

    public static function resolveDate(string $text, DateTimeImmutable $now): ?DateTimeImmutable
    {
        $dateString = self::extract($text);

        if (null === $dateString) {
            return null;
        }

        $parsed = self::parseNumeric($dateString) ?? self::parseText($dateString);

        if (null === $parsed) {
            return null;
        }

        if (null !== $parsed->year) {
            return self::createValidDate($parsed->year, $parsed->month, $parsed->day, $now);
        }

        return self::resolveClosestYear($parsed->day, $parsed->month, $now);
    }

    private static function textSubpattern(): string
    {
        $months = '(?:' . implode('|', array_keys(CalendarVocabulary::months())) . ')';
        $optionalOrdinal = '(?:' . self::ordinals() . ')?';
        $optionalPreposition = '(?:(?:' . self::prepositions() . ')\s+)?';
        $day = '\d{1,2}' . $optionalOrdinal;

        $dayBeforeMonth = $day . '\s+' . $optionalPreposition . $months;
        $monthBeforeDay = $months . '\s+' . $day;

        return $dayBeforeMonth . '|' . $monthBeforeDay;
    }

    /** The ordinal only ever follows the day number; unanchored it would eat the "st" in "august". */
    private static function nonMonthStripPattern(): string
    {
        return self::$nonMonthStripPattern ??= '/\d+(?:' . self::ordinals() . ')?|\b(?:' . self::prepositions() . ')\b/iu';
    }

    private static function ordinals(): string
    {
        return implode('|', CalendarVocabulary::ordinals());
    }

    private static function prepositions(): string
    {
        return implode('|', CalendarVocabulary::prepositions());
    }

    private static function parseNumeric(string $dateString): ?ParsedDate
    {
        if (1 !== preg_match(self::NUMERIC_PARSE_PATTERN, $dateString, $matches)) {
            return null;
        }

        return new ParsedDate(
            day: (int) $matches[1],
            month: (int) $matches[2],
            year: self::normalizeYear($matches[3] ?? null),
        );
    }

    private static function normalizeYear(?string $raw): ?int
    {
        if (null === $raw) {
            return null;
        }

        $year = (int) $raw;

        return 100 > $year ? $year + 2000 : $year;
    }

    private static function parseText(string $dateString): ?ParsedDate
    {
        if (1 !== preg_match(self::DAY_NUMBER_PATTERN, $dateString, $dayMatch)) {
            return null;
        }

        $day = (int) $dayMatch[0];
        $month = CalendarVocabulary::months()[self::toMonthName($dateString)] ?? null;

        if (null === $month) {
            return null;
        }

        return new ParsedDate($day, $month);
    }

    /** The month name alone — the day number, ordinal and preposition stripped off. */
    private static function toMonthName(string $dateString): string
    {
        return preg_replace(self::nonMonthStripPattern(), '', $dateString)
            |> trim(...)
            |> mb_strtolower(...);
    }

    private static function resolveClosestYear(int $day, int $month, DateTimeImmutable $now): ?DateTimeImmutable
    {
        $currentYear = (int) $now->format('Y');
        $closestDate = null;
        $smallestDistance = PHP_INT_MAX;

        foreach ([$currentYear - 1, $currentYear, $currentYear + 1] as $candidateYear) {
            $date = self::createValidDate($candidateYear, $month, $day, $now);

            if (null === $date) {
                continue;
            }

            $distance = abs($now->getTimestamp() - $date->getTimestamp());

            if ($distance < $smallestDistance) {
                $smallestDistance = $distance;
                $closestDate = $date;
            }
        }

        return $closestDate;
    }

    /** Built off $now so the date keeps the caller's timezone instead of the server's. */
    private static function createValidDate(int $year, int $month, int $day, DateTimeImmutable $now): ?DateTimeImmutable
    {
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return $now->setDate($year, $month, $day)->setTime(0, 0);
    }
}
