<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Common\GameDateResolver;

final class GameLabel
{
    public static function format(int $gameId, string $title): string
    {
        $parts = ["#$gameId"];

        $date = GameDateResolver::extractRaw($title);

        if (null !== $date) {
            $parts[] = $date;
        }

        $time = TimeExtractor::extract($title);

        if (null !== $time) {
            $parts[] = $time;
        }

        return implode(' ', $parts);
    }
}
