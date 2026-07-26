<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Telegram\MessageBuilders\KeyboardPagination;
use BeachVolleybot\Telegram\MessageBuilders\UserRoleListMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class UserRoleListMessageFactory
{
    private const int USERS_PER_PAGE = 8;

    public static function build(int $page): TelegramMessage
    {
        $userRepository = new UserRepository(Connection::get());
        $pagination = new KeyboardPagination($userRepository->countAll(), self::USERS_PER_PAGE, $page);
        $userRows = $userRepository->findAllPaginated(self::USERS_PER_PAGE, $pagination->getOffset());

        return new UserRoleListMessageBuilder()->build($userRows, $pagination);
    }
}
