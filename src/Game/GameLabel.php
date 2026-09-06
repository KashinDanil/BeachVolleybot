<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Localization\Translator;
use DateTimeImmutable;

/**
 * The label every game list button and inline article carries: the game number
 * and its kickoff, spelled out in the reader's language.
 */
final class GameLabel
{
    private const string SEPARATOR = ' · ';

    public static function format(
        int $gameId,
        DateTimeImmutable $kickoffAt,
        Translator $translator,
        DateTimeImmutable $now = new DateTimeImmutable(),
    ): string {
        $kickoff = self::formatKickoff($kickoffAt, $translator, $now);

        return "#$gameId" . self::SEPARATOR . $kickoff;
    }

    /** "Fri, 14 Aug 18:00" — the year only earns its place once the game is not from this one. */
    private static function formatKickoff(DateTimeImmutable $kickoffAt, Translator $translator, DateTimeImmutable $now): string
    {
        $parts = [
            $translator->translate($kickoffAt->format('D')) . ',',
            $kickoffAt->format('j'),
            $translator->translate($kickoffAt->format('M')),
        ];

        if ($kickoffAt->format('Y') !== $now->format('Y')) {
            $parts[] = $kickoffAt->format('Y');
        }

        $parts[] = $kickoffAt->format('H:i');

        return implode(' ', $parts);
    }
}
