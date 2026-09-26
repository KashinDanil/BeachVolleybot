<?php

declare(strict_types=1);

namespace BeachVolleybot\User;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;

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

    public function ensureUserRecord(TelegramUser $telegramUser): UserRecord
    {
        $this->userRepository->upsert(
            $telegramUser->id,
            $telegramUser->firstName,
            $telegramUser->lastName,
            $telegramUser->username,
        );

        return UserRecord::fromRow($this->userRepository->findById($telegramUser->id));
    }

    public function enableNotification(UserRecord $user, NotificationType $type): NotificationSettings
    {
        $notifications = $user->notifications->enable($type);
        $this->userRepository->updateNotifications($user->telegramUserId, $notifications);

        return $notifications;
    }

    public function disableNotification(UserRecord $user, NotificationType $type): NotificationSettings
    {
        $notifications = $user->notifications->disable($type);
        $this->userRepository->updateNotifications($user->telegramUserId, $notifications);

        return $notifications;
    }
}
