<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

final class DayOfWeekExtractor
{
    public const string PATTERN =
        '/(*UCP)\b(?:'
        . 'Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday|Today'
        . '|Понедельник|Вторник|Среда|Четверг|Пятница|Суббота|Воскресенье|Сегодня'
        . '|Lunes|Martes|Miércoles|Jueves|Viernes|Sábado|Domingo|Hoy'
        . ')\b/iu';

    public static function extract(string $text): ?string
    {
        if (1 !== preg_match(self::PATTERN, $text, $matches)) {
            return null;
        }

        return $matches[0];
    }
}
