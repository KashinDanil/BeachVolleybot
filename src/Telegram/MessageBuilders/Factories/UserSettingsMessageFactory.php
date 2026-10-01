<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Game\GameSlotManager;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\Telegram\MessageBuilders\Admin\UserSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\UserManager;

final class UserSettingsMessageFactory
{
    public static function build(int $gameId, int $telegramUserId): TelegramMessage
    {
        $gameUser = new GameUserManager()->findGameUserRecord($gameId, $telegramUserId);

        if (null === $gameUser) {
            return new UserSettingsMessageBuilder()->buildUserNotFound($gameId);
        }

        $user = new UserManager()->findUserRecordById($telegramUserId);
        $slotPositions = new GameSlotManager()->findPositionsByUser($gameId, $telegramUserId);

        return new UserSettingsMessageBuilder()->buildUserSettings(
            $gameId,
            $telegramUserId,
            $user,
            $slotPositions,
            $gameUser->volleyball,
            $gameUser->net,
        );
    }
}
