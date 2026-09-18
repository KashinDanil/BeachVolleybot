<?php

declare(strict_types=1);

namespace BeachVolleybot\Localization;

use DanilKashin\Localization\Language;

/**
 * Vocabulary the extractors read out of a game title.
 */
final class InputVocabulary
{
    /** @var array<string, array<string, int>> language => name => ISO weekday (1=Monday, 7=Sunday) */
    private const array WEEKDAYS = [
        Language::EN => [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ],
        Language::RU => [
            'понедельник' => 1,
            'вторник' => 2,
            'среда' => 3,
            'четверг' => 4,
            'пятница' => 5,
            'суббота' => 6,
            'воскресенье' => 7,
        ],
        Language::ES => [
            'lunes' => 1,
            'martes' => 2,
            'miércoles' => 3,
            'miercoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
            'sábado' => 6,
            'sabado' => 6,
            'domingo' => 7,
        ],
    ];

    /** @var array<string, array<string, int>> language => name => month number */
    private const array MONTHS = [
        Language::EN => [
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
        ],
        Language::RU => [
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
        ],
        Language::ES => [
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
        ],
    ];

    /** @var array<string, list<string>> language => suffixes */
    private const array ORDINALS = [
        Language::EN => ['st', 'nd', 'rd', 'th'],
    ];

    /** @var array<string, list<string>> language => prepositions */
    private const array PREPOSITIONS = [
        Language::EN => ['of'],
        Language::ES => ['de'],
    ];

    /** @var array<string, list<string>> language => nouns naming a roster slot */
    private const array SLOT_NOUNS = [
        Language::EN => ['spots', 'spot', 'slots', 'slot', 'places', 'place', 'spaces', 'space', 'people', 'players'],
        Language::RU => ['мест', 'места', 'место', 'человек', 'человека', 'игроков', 'игрока'],
        Language::ES => ['plazas', 'plaza', 'cupos', 'cupo', 'huecos', 'hueco', 'personas', 'jugadores'],
    ];

    /**
     * @var array<string, list<string>> language => nouns naming a net; singular only, since the
     *      count is always "per one net". The Russian forms are accusative/prepositional only —
     *      "на" never governs nominative ("сетка") or genitive ("корта"), so those never appear.
     */
    private const array NET_NOUNS = [
        Language::EN => ['net', 'court'],
        Language::RU => ['сетку', 'сетке', 'корт', 'корте'],
        Language::ES => ['red', 'cancha', 'pista'],
    ];

    /** @var array<string, list<string>> language => the preposition linking a slot count to a net */
    private const array PER_PREPOSITIONS = [
        Language::EN => ['per'],
        Language::RU => ['на'],
        Language::ES => ['por'],
    ];

    /** @var array<string, int>|null */
    private static ?array $weekdays = null;

    /** @var array<string, int>|null */
    private static ?array $months = null;

    /** @var array<string, string>|null */
    private static ?array $languageByName = null;

    /** @return list<string> */
    public static function languages(): array
    {
        return array_keys(self::WEEKDAYS);
    }

    /** @return array<string, int> */
    public static function weekdays(): array
    {
        return self::$weekdays ??= self::flatten(self::WEEKDAYS);
    }

    /** @return array<string, int> */
    public static function months(): array
    {
        return self::$months ??= self::flatten(self::MONTHS);
    }

    /**
     * Every weekday and month name mapped back to the language that declares it, so a name
     * matched in a title can be traced to the language it was written in.
     *
     * @return array<string, string> name => language
     */
    public static function languageByName(): array
    {
        return self::$languageByName ??= self::buildLanguageByName();
    }

    /** @return list<string> */
    public static function ordinals(): array
    {
        return self::flatten(self::ORDINALS);
    }

    /** @return list<string> */
    public static function prepositions(): array
    {
        return self::flatten(self::PREPOSITIONS);
    }

    /** @return list<string> */
    public static function slotNouns(): array
    {
        return self::flatten(self::SLOT_NOUNS);
    }

    /** @return list<string> */
    public static function netNouns(): array
    {
        return self::flatten(self::NET_NOUNS);
    }

    /** @return list<string> */
    public static function perPrepositions(): array
    {
        return self::flatten(self::PER_PREPOSITIONS);
    }

    /**
     * @template T
     *
     * @param array<string, array<array-key, T>> $byLanguage
     *
     * @return array<array-key, T>
     */
    private static function flatten(array $byLanguage): array
    {
        return array_merge(...array_values($byLanguage));
    }

    /** @return array<string, string> */
    private static function buildLanguageByName(): array
    {
        $languageByName = [];

        foreach ([self::WEEKDAYS, self::MONTHS] as $namesByLanguage) {
            foreach ($namesByLanguage as $language => $names) {
                $languageByName += array_fill_keys(array_keys($names), $language);
            }
        }

        return $languageByName;
    }
}
