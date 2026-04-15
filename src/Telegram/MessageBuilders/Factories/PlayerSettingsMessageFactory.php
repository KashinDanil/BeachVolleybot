<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\PlayerRepository;
use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Telegram\MessageBuilders\PlayerSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use Medoo\Medoo;

final class PlayerSettingsMessageFactory
{
    public static function build(int $gameId, int $telegramUserId): TelegramMessage
    {
        $db = Connection::get();
        $gamePlayerRow = new GamePlayerRepository($db)->findByGamePlayer($gameId, $telegramUserId);

        if (null === $gamePlayerRow) {
            return new PlayerSettingsMessageBuilder()->buildPlayerNotFound($gameId);
        }

        $playerName = self::resolvePlayerName($db, $telegramUserId);
        $slotCount = count(new GameSlotRepository($db)->findPositionsByPlayer($gameId, $telegramUserId));

        return new PlayerSettingsMessageBuilder()->buildPlayerSettings(
            $gameId,
            $telegramUserId,
            $playerName,
            $slotCount,
            (int)($gamePlayerRow['volleyball'] ?? 0),
            (int)($gamePlayerRow['net'] ?? 0),
        );
    }

    private static function resolvePlayerName(Medoo $db, int $telegramUserId): string
    {
        $playerRow = new PlayerRepository($db)->findById($telegramUserId);

        if (null === $playerRow) {
            return "User $telegramUserId";
        }

        return Player::buildName($playerRow['first_name'], $playerRow['last_name'] ?? null);
    }
}
