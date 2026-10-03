<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Localization\Translator;
use DateTimeImmutable;

/** Spells out the parts of a kickoff in the reader's language, on the venue's wall clock. */
final readonly class KickoffFormatter
{
    public function __construct(
        private DateTimeImmutable $kickoffAt,
        private Translator $translator,
        private DateTimeImmutable $now = new DateTimeImmutable(),
    ) {
    }

    /** "Fri" */
    public function formatShortWeekday(): string
    {
        return $this->translator->translate($this->kickoffAt->format('D'));
    }

    /** "on Friday" — the preposition is translated with the day, since ru/es inflect the weekday after it. */
    public function formatWeekdayWithPreposition(): string
    {
        return $this->translator->translate('on ' . $this->kickoffAt->format('l'));
    }

    /** "14 Aug" — the year only earns its place once the game is not from this one. */
    public function formatDayAndMonth(): string
    {
        $parts = [
            $this->kickoffAt->format('j'),
            $this->translator->translate($this->kickoffAt->format('M')),
        ];

        if (!$this->isKickoffInCurrentYear()) {
            $parts[] = $this->kickoffAt->format('Y');
        }

        return implode(' ', $parts);
    }

    /** "18:00" */
    public function formatTime(): string
    {
        return $this->kickoffAt->format('H:i');
    }

    private function isKickoffInCurrentYear(): bool
    {
        $nowAtTheVenue = $this->now->setTimezone($this->kickoffAt->getTimezone());

        return $nowAtTheVenue->format('Y') === $this->kickoffAt->format('Y');
    }
}
