<?php

declare(strict_types=1);

namespace BeachVolleybot\Localization;

final readonly class HoursPhrase
{
    private const string PHRASE_KEY = '%d %s';
    private const string NOUN_KEY = 'one:hour;other:hours';

    public function __construct(
        private int $count,
        private Translator $translator,
    ) {
    }

    public function text(): string
    {
        return sprintf(self::PHRASE_KEY, $this->count, $this->noun());
    }

    private function noun(): string
    {
        $category = new PluralRules($this->translator->language(), $this->count)->category();

        return new PluralForms($this->translator->translate(self::NOUN_KEY))->get($category);
    }
}
