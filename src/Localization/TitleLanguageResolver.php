<?php

declare(strict_types=1);

namespace BeachVolleybot\Localization;

use BeachVolleybot\Common\Extractors\DateExtractor;
use BeachVolleybot\Common\Extractors\DayOfWeekExtractor;
use DanilKashin\Localization\Language;

final class TitleLanguageResolver
{
    /**
     * The language a title is written in, read off the weekday or month name it carries.
     * A numeric date ("31.12") names no language, so it falls back to English.
     */
    public static function resolve(string $title): string
    {
        $calendarName = DayOfWeekExtractor::extract($title) ?? DateExtractor::extractMonthName($title);

        if (null === $calendarName) {
            return Language::EN;
        }

        return InputVocabulary::languageByName()[mb_strtolower($calendarName)] ?? Language::EN;
    }
}
