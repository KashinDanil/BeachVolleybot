<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\Extractors;

use BeachVolleybot\Localization\CalendarVocabulary;
use DateTimeImmutable;

final class DayOfWeekExtractor implements ExtractorInterface
{
    private static ?string $pattern = null;

    public static function pattern(): string
    {
        return self::$pattern ??= '/(*UCP)\b(?:' . implode('|', array_keys(CalendarVocabulary::weekdays())) . ')\b/iu';
    }

    public static function extract(string $text): ?string
    {
        if (1 !== preg_match(self::pattern(), $text, $matches)) {
            return null;
        }

        return $matches[0];
    }

    public static function resolveDate(string $text, DateTimeImmutable $creationDate): ?DateTimeImmutable
    {
        $dayOfWeekString = self::extract($text);

        if (null === $dayOfWeekString) {
            return null;
        }

        $targetDay = self::toIsoDayNumber($dayOfWeekString);

        if (null === $targetDay) {
            return null;
        }

        $creationDayOfWeek = (int)$creationDate->format('N');
        $daysUntilTarget = ($targetDay - $creationDayOfWeek + 7) % 7;

        return $creationDate->modify("+{$daysUntilTarget} days")->setTime(0, 0);
    }

    private static function toIsoDayNumber(string $matched): ?int
    {
        return CalendarVocabulary::weekdays()[mb_strtolower($matched)] ?? null;
    }
}