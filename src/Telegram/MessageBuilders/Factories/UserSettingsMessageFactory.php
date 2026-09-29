<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Telegram\MessageBuilders\Admin\UserSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\UserManager;

final class UserSettingsMessageFactory
{
    public static function build(int $gameId, int $telegramUserId): TelegramMessage
    {
        $db = Connection::get();
        $gameUserRow = new GameUserRepository($db)->findByGameUser($gameId, $telegramUserId);

        if (null === $gameUserRow) {
            return new UserSettingsMessageBuilder()->buildUserNotFound($gameId);
        }

        $user = new UserManager()->findUserRecordById($telegramUserId);
        $slotPositions = new GameSlotRepository($db)->findPositionsByUser($gameId, $telegramUserId);

        return new UserSettingsMessageBuilder()->buildUserSettings(
            $gameId,
            $telegramUserId,
            $user,
            $slotPositions,
            (int)($gameUserRow['volleyball'] ?? 0),
            (int)($gameUserRow['net'] ?? 0),
        );
    }
}
