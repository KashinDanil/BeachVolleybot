<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

use BeachVolleybot\Common\Extractors\DateExtractor;
use BeachVolleybot\Common\Extractors\DayOfWeekExtractor;
use DateTimeImmutable;

final class GameDateResolver
{
    public static function extractRaw(string $title): ?string
    {
        return DateExtractor::extract($title)
            ?? DayOfWeekExtractor::extract($title);
    }

    public static function resolve(string $title, DateTimeImmutable $creationDate): ?DateTimeImmutable
    {
        return DateExtractor::resolveDate($title, $creationDate)
            ?? DayOfWeekExtractor::resolveDate($title, $creationDate);
    }
}
