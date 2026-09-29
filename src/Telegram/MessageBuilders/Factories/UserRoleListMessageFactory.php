<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Telegram\MessageBuilders\Admin\UserRoleListMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\KeyboardPagination;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\UserManager;

final class UserRoleListMessageFactory
{
    private const int USERS_PER_PAGE = 8;

    public static function build(int $page): TelegramMessage
    {
        $userManager = new UserManager();
        $pagination = new KeyboardPagination($userManager->countUsers(), self::USERS_PER_PAGE, $page);
        $users = $userManager->findUserRecordsPage(self::USERS_PER_PAGE, $pagination->getOffset());

        return new UserRoleListMessageBuilder()->build($users, $pagination);
    }
}
