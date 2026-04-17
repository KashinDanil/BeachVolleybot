<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Common\Extractors\DateExtractor;
use BeachVolleybot\Common\Extractors\DayOfWeekExtractor;
use BeachVolleybot\Common\Extractors\TimeExtractor;
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
            ...self::findAll(TimeExtractor::pattern(), $title, $formatter->underline(...)),
            ...self::findAll(DayOfWeekExtractor::pattern(), $title, $formatter->italic(...)),
            ...self::findAll(DateExtractor::pattern(), $title, $formatter->italic(...)),
        ];

        usort($segments, static fn(TitleSegment $a, TitleSegment $b) => $a->offset <=> $b->offset);

        return self::render($title, $segments, $formatter);
    }

    /** @return list<TitleSegment> */
    private static function findAll(string $pattern, string $title, Closure $style): array
    {
        preg_match_all($pattern, $title, $matches, PREG_OFFSET_CAPTURE);

        return array_map(
            static fn(array $match) => new TitleSegment($match[1], strlen($match[0]), $style),
            $matches[0],
        );
    }

    /** @param list<TitleSegment> $segments */
    private static function render(string $title, array $segments, MarkdownV2 $formatter): string
    {
        $result = '';
        $position = 0;

        foreach ($segments as $segment) {
            if ($segment->offset > $position) {
                $result .= $formatter->escape(substr($title, $position, $segment->offset - $position));
            }

            $style = $segment->style;
            $result .= substr($title, $segment->offset, $segment->length) |> $style(...);
            $position = $segment->offset + $segment->length;
        }

        if ($position < strlen($title)) {
            $result .= $formatter->escape(substr($title, $position));
        }

        return $result;
    }
}
