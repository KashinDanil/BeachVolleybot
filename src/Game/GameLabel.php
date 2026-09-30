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

    /** "Fri, 14 Aug 18:00" */
    private static function formatKickoff(DateTimeImmutable $kickoffAt, Translator $translator, DateTimeImmutable $now): string
    {
        $kickoffFormatter = new KickoffFormatter($kickoffAt, $translator, $now);

        return $kickoffFormatter->formatShortWeekday()
            . ', ' . $kickoffFormatter->formatDayAndMonth()
            . ' ' . $kickoffFormatter->formatTime();
    }
}
