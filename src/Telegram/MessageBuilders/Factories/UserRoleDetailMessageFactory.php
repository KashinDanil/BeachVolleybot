<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Telegram\MessageBuilders\Admin\UserRoleDetailMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\UserManager;

final class UserRoleDetailMessageFactory
{
    public static function build(int $telegramUserId): TelegramMessage
    {
        $user = new UserManager()->findUserRecordById($telegramUserId);

        if (null === $user) {
            return new UserRoleDetailMessageBuilder()->buildUserNotFound();
        }

        return new UserRoleDetailMessageBuilder()->buildUserDetail($user);
    }
}
