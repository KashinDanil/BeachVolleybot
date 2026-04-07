<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\GameWithTime;

final class EmojifyAddOn implements GameAddOnInterface
{
    private const array DIGIT_EMOJIS = [
        '0' => '0️⃣', '1' => '1️⃣', '2' => '2️⃣', '3' => '3️⃣', '4' => '4️⃣',
        '5' => '5️⃣', '6' => '6️⃣', '7' => '7️⃣', '8' => '8️⃣', '9' => '9️⃣',
    ];

    public function transform(GameInterface $game): GameInterface
    {
        return new GameWithTime(
            gameId: $game->getGameId(),
            inlineQueryId: $game->getInlineQueryId(),
            inlineMessageId: $game->getInlineMessageId(),
            title: $this->emojifyTime($game->getTitle()),
            players: $game->getPlayers(),
            time: $game->getTime(),
            location: $game->getLocation(),
        );
    }

    private function emojifyTime(string $text): string
    {
        return preg_replace_callback('/\b(\d{1,2}):(\d{2})\b/', function (array $matches): string {
            return $this->digitToEmoji($matches[1]) . ':' . $this->digitToEmoji($matches[2]);
        }, $text);
    }

    private function digitToEmoji(string $digits): string
    {
        return $digits
                |> str_split(...)
                |> (static fn($x) => array_map(fn(string $char) => self::DIGIT_EMOJIS[$char], $x))
                |> (static fn($x) => implode('', $x));
    }
}
