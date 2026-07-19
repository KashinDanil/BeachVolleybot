<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Telegram\MessageBuilders\UserSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class UserSettingsMessageFactory
{
    public static function build(int $gameId, int $telegramUserId): TelegramMessage
    {
        $db = Connection::get();
        $gameUserRow = new GameUserRepository($db)->findByGameUser($gameId, $telegramUserId);

        if (null === $gameUserRow) {
            return new UserSettingsMessageBuilder()->buildUserNotFound($gameId);
        }

        $userRow = new UserRepository($db)->findById($telegramUserId);
        $slotCount = count(new GameSlotRepository($db)->findPositionsByUser($gameId, $telegramUserId));

        return new UserSettingsMessageBuilder()->buildUserSettings(
            $gameId,
            $telegramUserId,
            $userRow,
            $slotCount,
            (int)($gameUserRow['volleyball'] ?? 0),
            (int)($gameUserRow['net'] ?? 0),
        );
    }
}
