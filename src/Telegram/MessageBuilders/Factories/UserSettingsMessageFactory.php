<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Game\Models\User;
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
        $userName = null !== $userRow
            ? User::buildName($userRow['first_name'], $userRow['last_name'] ?? null)
            : "User $telegramUserId";
        $userLink = null !== $userRow ? User::buildLink($userRow['username'] ?? null) : null;

        $slotCount = count(new GameSlotRepository($db)->findPositionsByUser($gameId, $telegramUserId));

        return new UserSettingsMessageBuilder()->buildUserSettings(
            $gameId,
            $telegramUserId,
            $userName,
            $userLink,
            $slotCount,
            (int)($gameUserRow['volleyball'] ?? 0),
            (int)($gameUserRow['net'] ?? 0),
        );
    }
}
