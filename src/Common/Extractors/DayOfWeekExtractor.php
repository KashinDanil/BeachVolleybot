<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\Extractors;

use DateTimeImmutable;

final class DayOfWeekExtractor implements ExtractorInterface
{
    /** @var array<string, int> ISO day-of-week (1=Monday, 7=Sunday). Single source of truth for day names. */
    public const array DAY_OF_WEEK_MAP = [
        // English
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7,
        // Russian
        'понедельник' => 1,
        'вторник' => 2,
        'среда' => 3,
        'четверг' => 4,
        'пятница' => 5,
        'суббота' => 6,
        'воскресенье' => 7,
        // Spanish
        'lunes' => 1,
        'martes' => 2,
        'miércoles' => 3,
        'jueves' => 4,
        'viernes' => 5,
        'sábado' => 6,
        'domingo' => 7,
    ];

    private static ?string $pattern = null;

    public static function pattern(): string
    {
        return self::$pattern ??= '/(*UCP)\b(?:' . implode('|', array_keys(self::DAY_OF_WEEK_MAP)) . ')\b/iu';
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
        return self::DAY_OF_WEEK_MAP[mb_strtolower($matched)] ?? null;
    }
}