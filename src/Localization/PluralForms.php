<?php

declare(strict_types=1);

namespace BeachVolleybot\Localization;

/**
 * The word(s) one translation key holds for a pluralized noun. A language needing only the
 * `other` category (en, es) translates the key to a bare word; one needing more writes
 * `category:word` pairs separated by `;`, e.g. `one:игрок;few:игрока;many:игроков` — order
 * does not matter, and a category the value does not list falls back to `other`.
 */
final readonly class PluralForms
{
    private const string PAIR_SEPARATOR = ';';
    private const string LABEL_SEPARATOR = ':';

    /** @var array<string, string> */
    private array $wordsByCategory;

    public function __construct(string $translated)
    {
        $this->wordsByCategory = $this->parse($translated);
    }

    public function get(PluralCategory $category): string
    {
        return $this->wordsByCategory[$category->value] ?? $this->wordsByCategory[PluralCategory::Other->value];
    }

    /** @return array<string, string> */
    private function parse(string $translated): array
    {
        if (!str_contains($translated, self::LABEL_SEPARATOR)) {
            return [PluralCategory::Other->value => $translated];
        }

        $wordsByCategory = [];

        foreach (explode(self::PAIR_SEPARATOR, $translated) as $pair) {
            $labelAndWord = explode(self::LABEL_SEPARATOR, $pair, 2);
            $wordsByCategory[$labelAndWord[0]] = $labelAndWord[1];
        }

        return $wordsByCategory;
    }
}
