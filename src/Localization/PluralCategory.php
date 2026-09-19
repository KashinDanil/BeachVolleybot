<?php

declare(strict_types=1);

namespace BeachVolleybot\Localization;

/**
 * The CLDR plural categories (https://cldr.unicode.org/index/cldr-spec/plural-rules). No
 * language uses all six; `Other` is the one every language must define, since it is the
 * catch-all a count falls into when none of a language's more specific categories apply.
 */
enum PluralCategory: string
{
    case Zero  = 'zero';
    case One   = 'one';
    case Two   = 'two';
    case Few   = 'few';
    case Many  = 'many';
    case Other = 'other';
}
