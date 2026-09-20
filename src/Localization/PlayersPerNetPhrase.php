<?php

declare(strict_types=1);

namespace BeachVolleybot\Localization;

final readonly class PlayersPerNetPhrase
{
    private const string PHRASE_KEY = '%d %s per net';
    private const string NOUN_KEY = 'spots';

    public function __construct(
        private int $count,
        private Translator $translator,
    ) {
    }

    public function text(): string
    {
        return sprintf($this->translator->translate(self::PHRASE_KEY), $this->count, $this->noun());
    }

    private function noun(): string
    {
        $category = new PluralRules($this->translator->language(), $this->count)->category();

        return new PluralForms($this->translator->translate(self::NOUN_KEY))->get($category);
    }
}
