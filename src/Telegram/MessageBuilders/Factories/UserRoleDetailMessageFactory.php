<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Telegram\MessageBuilders\UserRoleDetailMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class UserRoleDetailMessageFactory
{
    public static function build(int $telegramUserId): TelegramMessage
    {
        $userRow = new UserRepository(Connection::get())->findById($telegramUserId);

        if (null === $userRow) {
            return new UserRoleDetailMessageBuilder()->buildUserNotFound();
        }

        return new UserRoleDetailMessageBuilder()->buildUserDetail($userRow);
    }
}
