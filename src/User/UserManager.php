<?php

declare(strict_types=1);

namespace BeachVolleybot\User;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use DanilKashin\Localization\Language;

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

    /**
     * @param list<int> $telegramUserIds
     *
     * @return list<UserRecord>
     */
    public function findUserRecordsByIds(array $telegramUserIds): array
    {
        return $this->toUserRecords($this->userRepository->findByIds($telegramUserIds));
    }

    /** @return list<UserRecord> */
    public function findUserRecordsPage(int $limit, int $offset): array
    {
        return $this->toUserRecords($this->userRepository->findAllPaginated($limit, $offset));
    }

    public function countUsers(): int
    {
        return $this->userRepository->countAll();
    }

    public function upsertUser(TelegramUser $telegramUser): void
    {
        $this->userRepository->upsert(
            $telegramUser->id,
            $telegramUser->firstName,
            $telegramUser->lastName,
            $telegramUser->username,
            $this->normalizeLanguageCode($telegramUser->languageCode),
        );
    }

    public function ensureUserRecord(TelegramUser $telegramUser): UserRecord
    {
        $this->upsertUser($telegramUser);

        return UserRecord::fromRow($this->userRepository->findById($telegramUser->id));
    }

    public function changeRole(UserRecord $user, Role $role): void
    {
        $this->userRepository->updateRole($user->telegramUserId, $role->value);
    }

    public function enableNotification(UserRecord $user, NotificationType $type): NotificationSettings
    {
        $notifications = $user->notifications->enable($type);
        $this->userRepository->updateNotifications($user->telegramUserId, $notifications->toInt());

        return $notifications;
    }

    public function disableNotification(UserRecord $user, NotificationType $type): NotificationSettings
    {
        $notifications = $user->notifications->disable($type);
        $this->userRepository->updateNotifications($user->telegramUserId, $notifications->toInt());

        return $notifications;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<UserRecord>
     */
    private function toUserRecords(array $rows): array
    {
        return array_map(UserRecord::fromRow(...), $rows);
    }

    private function normalizeLanguageCode(?string $languageCode): ?string
    {
        return null !== $languageCode ? Language::fromCode($languageCode) : null;
    }
}
