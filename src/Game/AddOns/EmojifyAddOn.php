<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Common\TimeExtractor;
use BeachVolleybot\Game\Models\Game;

final class EmojifyAddOn implements GameAddOnInterface
{
    private const array DIGIT_EMOJIS = [
        '0' => '0️⃣', '1' => '1️⃣', '2' => '2️⃣', '3' => '3️⃣', '4' => '4️⃣',
        '5' => '5️⃣', '6' => '6️⃣', '7' => '7️⃣', '8' => '8️⃣', '9' => '9️⃣',
    ];

    public function transform(Game $game): void
    {
        $game->title = $this->emojifyTime($game->title);
    }

    private function emojifyTime(string $text): string
    {
        return preg_replace_callback(TimeExtractor::PATTERN, function (array $matches): string {
            return $this->digitToEmoji($matches[1]) . ':' . $this->digitToEmoji($matches[2]);
        }, $text);
    }

    private function digitToEmoji(string $digits): string
    {
        return $digits
                |> str_split(...)
                |> (static fn($x) => array_map(static fn(string $char) => self::DIGIT_EMOJIS[$char], $x))
                |> (static fn($x) => implode('', $x));
    }
}
