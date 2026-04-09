<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

final class DateExtractor
{
    private const string MONTHS =
        'January|February|March|April|May|June|July|August|September|October|November|December'
        . '|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Oct|Nov|Dec'
        . '|января|февраля|марта|апреля|мая|июня|июля|августа|сентября|октября|ноября|декабря'
        . '|январь|февраль|март|апрель|май|июнь|июль|август|сентябрь|октябрь|ноябрь|декабрь'
        . '|янв|фев|мар|апр|июн|июл|авг|сен|окт|ноя|дек'
        . '|enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre'
        . '|ene|abr|ago|dic';

    private const string ORDINAL = '(?:st|nd|rd|th)';

    private const string NUMBERS_SUBPATTERN = '\d{1,2}\.\d{2}(?:\.\d{2,4})?';

    private const string TEXT_SUBPATTERN =
        '\d{1,2}' . self::ORDINAL . '?\s+(?:(?:of|de)\s+)?(?:' . self::MONTHS . ')'
        . '|(?:' . self::MONTHS . ')\s+\d{1,2}' . self::ORDINAL . '?';

    public const string PATTERN         = '/(*UCP)\b(?:' . self::NUMBERS_SUBPATTERN . '|' . self::TEXT_SUBPATTERN . ')\b/iu';
    public const string NUMBERS_PATTERN = '/\b' . self::NUMBERS_SUBPATTERN . '\b/';
    public const string TEXT_PATTERN    = '/(*UCP)\b(?:' . self::TEXT_SUBPATTERN . ')\b/iu';

    public static function extract(string $text): ?string
    {
        if (1 !== preg_match(self::PATTERN, $text, $matches)) {
            return null;
        }

        return $matches[0];
    }
}
