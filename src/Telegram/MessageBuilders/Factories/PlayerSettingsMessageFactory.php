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

final class PlayerSettingsMessageFactory
{
    public static function build(int $gameId, int $telegramUserId): TelegramMessage
    {
        $db = Connection::get();
        $gamePlayerRow = new GamePlayerRepository($db)->findByGamePlayer($gameId, $telegramUserId);

        if (null === $gamePlayerRow) {
            return new PlayerSettingsMessageBuilder()->buildPlayerNotFound($gameId);
        }

        $playerRow = new PlayerRepository($db)->findById($telegramUserId);
        $playerName = null !== $playerRow
            ? Player::buildName($playerRow['first_name'], $playerRow['last_name'] ?? null)
            : "User $telegramUserId";
        $playerLink = null !== $playerRow ? Player::buildLink($playerRow['username'] ?? null) : null;

        $slotCount = count(new GameSlotRepository($db)->findPositionsByPlayer($gameId, $telegramUserId));

        return new PlayerSettingsMessageBuilder()->buildPlayerSettings(
            $gameId,
            $telegramUserId,
            $playerName,
            $playerLink,
            $slotCount,
            (int)($gamePlayerRow['volleyball'] ?? 0),
            (int)($gamePlayerRow['net'] ?? 0),
        );
    }
}
