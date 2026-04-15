<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Common\DateExtractor;
use BeachVolleybot\Common\DayOfWeekExtractor;
use BeachVolleybot\Common\TimeExtractor;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\MarkdownV2;
use Closure;

final class StylizeTitleAddOn implements GameAddOnInterface
{
    public function applyTo(Game $game): void
    {
        $game->telegramMessageBuilder->override('buildTitle', self::buildTitle(...));
    }

    private static function buildTitle(GameInterface $game): string
    {
        $formatter = new MarkdownV2();
        $title = $game->getTitle();

        $segments = [
            ...self::findAll(TimeExtractor::PATTERN, $title, $formatter->underline(...)),
            ...self::findAll(DayOfWeekExtractor::PATTERN, $title, $formatter->italic(...)),
            ...self::findAll(DateExtractor::PATTERN, $title, $formatter->italic(...)),
        ];

        usort($segments, static fn(array $a, array $b) => $a[0] <=> $b[0]);

        return self::render($title, $segments, $formatter);
    }

    /** @return list<array{int, int, Closure}> */
    private static function findAll(string $pattern, string $title, Closure $style): array
    {
        preg_match_all($pattern, $title, $matches, PREG_OFFSET_CAPTURE);

        return array_map(
            static fn(array $match) => [$match[1], strlen($match[0]), $style],
            $matches[0],
        );
    }

    private static function render(string $title, array $segments, MarkdownV2 $formatter): string
    {
        $result = '';
        $position = 0;

        foreach ($segments as [$offset, $length, $style]) {
            if ($offset > $position) {
                $result .= $formatter->escape(substr($title, $position, $offset - $position));
            }

            $result .= substr($title, $offset, $length) |> $style(...);
            $position = $offset + $length;
        }

        if ($position < strlen($title)) {
            $result .= $formatter->escape(substr($title, $position));
        }

        return $result;
    }
}
