<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\Extractors;

use BeachVolleybot\Common\ParsedDate;
use DateTimeImmutable;

final class DateExtractor implements ExtractorInterface
{
    /** @var array<string, int> Single source of truth for month name → number mapping */
    public const array MONTH_MAP = [
        // English full
        'january' => 1,
        'february' => 2,
        'march' => 3,
        'april' => 4,
        'may' => 5,
        'june' => 6,
        'july' => 7,
        'august' => 8,
        'september' => 9,
        'october' => 10,
        'november' => 11,
        'december' => 12,
        // English short
        'jan' => 1,
        'feb' => 2,
        'mar' => 3,
        'apr' => 4,
        'jun' => 6,
        'jul' => 7,
        'aug' => 8,
        'sep' => 9,
        'oct' => 10,
        'nov' => 11,
        'dec' => 12,
        // Russian genitive
        'января' => 1,
        'февраля' => 2,
        'марта' => 3,
        'апреля' => 4,
        'мая' => 5,
        'июня' => 6,
        'июля' => 7,
        'августа' => 8,
        'сентября' => 9,
        'октября' => 10,
        'ноября' => 11,
        'декабря' => 12,
        // Russian nominative
        'январь' => 1,
        'февраль' => 2,
        'март' => 3,
        'апрель' => 4,
        'май' => 5,
        'июнь' => 6,
        'июль' => 7,
        'август' => 8,
        'сентябрь' => 9,
        'октябрь' => 10,
        'ноябрь' => 11,
        'декабрь' => 12,
        // Russian short
        'янв' => 1,
        'фев' => 2,
        'мар' => 3,
        'апр' => 4,
        'июн' => 6,
        'июл' => 7,
        'авг' => 8,
        'сен' => 9,
        'окт' => 10,
        'ноя' => 11,
        'дек' => 12,
        // Spanish full
        'enero' => 1,
        'febrero' => 2,
        'marzo' => 3,
        'abril' => 4,
        'mayo' => 5,
        'junio' => 6,
        'julio' => 7,
        'agosto' => 8,
        'septiembre' => 9,
        'octubre' => 10,
        'noviembre' => 11,
        'diciembre' => 12,
        // Spanish short
        'ene' => 1,
        'abr' => 4,
        'ago' => 8,
        'dic' => 12,
    ];

    private const string ORDINAL_SUFFIXES = 'st|nd|rd|th';
    private const string PREPOSITIONS = 'of|de';
    private const string NUMBERS_SUBPATTERN = '\d{1,2}\.\d{2}(?:\.\d{2,4})?';
    public const string NUMBERS_PATTERN = '/\b' . self::NUMBERS_SUBPATTERN . '\b/';

    private const string NUMERIC_PARSE_PATTERN = '/^(\d{1,2})\.(\d{2})(?:\.(\d{2,4}))?$/';
    private const string DAY_NUMBER_PATTERN = '/\d{1,2}/';
    private const string NON_MONTH_STRIP_PATTERN = '/\d+|' . self::ORDINAL_SUFFIXES . '|\b(?:' . self::PREPOSITIONS . ')\b/iu';

    private static ?string $pattern = null;

    private static ?string $textPattern = null;

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
            return self::createValidDate($parsed->year, $parsed->month, $parsed->day);
        }

        return self::resolveClosestYear($parsed->day, $parsed->month, $now);
    }

    private static function textSubpattern(): string
    {
        $months = '(?:' . implode('|', array_keys(self::MONTH_MAP)) . ')';
        $optionalOrdinal = '(?:' . self::ORDINAL_SUFFIXES . ')?';
        $optionalPreposition = '(?:(?:' . self::PREPOSITIONS . ')\s+)?';
        $day = '\d{1,2}' . $optionalOrdinal;

        $dayBeforeMonth = $day . '\s+' . $optionalPreposition . $months;
        $monthBeforeDay = $months . '\s+' . $day;

        return $dayBeforeMonth . '|' . $monthBeforeDay;
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
        $monthName = preg_replace(self::NON_MONTH_STRIP_PATTERN, '', $dateString);
        $monthName = mb_strtolower(trim($monthName));
        $month = self::MONTH_MAP[$monthName] ?? null;

        if (null === $month) {
            return null;
        }

        return new ParsedDate($day, $month);
    }

    private static function resolveClosestYear(int $day, int $month, DateTimeImmutable $now): ?DateTimeImmutable
    {
        $currentYear = (int) $now->format('Y');
        $closestDate = null;
        $smallestDistance = PHP_INT_MAX;

        foreach ([$currentYear - 1, $currentYear, $currentYear + 1] as $candidateYear) {
            $date = self::createValidDate($candidateYear, $month, $day);

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

    private static function createValidDate(int $year, int $month, int $day): ?DateTimeImmutable
    {
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return new DateTimeImmutable()->setDate($year, $month, $day)->setTime(0, 0);
    }
}
