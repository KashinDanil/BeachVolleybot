<?php

declare(strict_types=1);

namespace BeachVolleybot\User;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\UserRepository;

readonly class UserManager
{
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository(Connection::get());
    }

    public function findUserRecordById(int $telegramUserId): ?UserRecord
    {
        $row = $this->userRepository->findById($telegramUserId);

        return null !== $row ? UserRecord::fromRow($row) : null;
    }

    public function enableNotification(UserRecord $user, NotificationType $type): void
    {
        $this->userRepository->updateNotifications($user->telegramUserId, $user->notifications->enable($type));
    }

    public function disableNotification(UserRecord $user, NotificationType $type): void
    {
        $this->userRepository->updateNotifications($user->telegramUserId, $user->notifications->disable($type));
    }
}
