<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Admin;

use BeachVolleybot\Database\Timestamp;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\ProfileNameFormatter;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserRecord;

final class UserRoleDetailMessageBuilder extends AbstractAdminMessageBuilder
{
    private const string HEADER_MESSAGE   = 'User';
    private const string PROMOTE_TO_ADMIN = 'Promote to Admin';
    private const string DEMOTE_TO_PLAYER = 'Demote to Player';
    private const string NOTIFICATIONS = 'Notifications';
    private const string OPTED_IN         = 'Opted in';

    public function buildUserDetail(UserRecord $user): TelegramMessage
    {
        return $this->buildMessage(
            $this->buildUserDetailText($user),
            $this->buildUserDetailKeyboard($user),
        );
    }

    private function buildUserDetailText(UserRecord $user): string
    {
        return implode($this->formatter->newLine(), [
            $this->formatHeader(self::HEADER_MESSAGE),
            new ProfileNameFormatter($this->formatter)->formatUser($user),
            $this->formatter->escape('Username: ' . $this->formatUsername($user)),
            $this->formatter->escape("Telegram ID: ") . $this->formatter->code((string)$user->telegramUserId),
            $this->formatter->escape("Role: ") . $user->role->name,
            $this->formatter->escape('Language: ' . ($user->languageCode ?? '—')),
            $this->formatter->escape('Notifications: ' . $this->formatNotificationsOptIn($user)),
            $this->formatter->escape('Created: ' . Timestamp::format($user->createdAt) . ' UTC'),
            $this->formatter->escape('Updated: ' . Timestamp::format($user->updatedAt) . ' UTC'),
        ]);
    }

    private function formatUsername(UserRecord $user): string
    {
        if (null === $user->username) {
            return '—';
        }

        return "@$user->username";
    }

    private function formatNotificationsOptIn(UserRecord $user): string
    {
        if (null === $user->notifications) {
            return '—';
        }

        return self::OPTED_IN;
    }

    private function buildUserDetailKeyboard(UserRecord $user): array
    {
        $keyboard = [];

        $roleActionRow = $this->buildRoleActionRow($user->telegramUserId, $user->role);
        if (null !== $roleActionRow) {
            $keyboard[] = $roleActionRow;
        }

        if (null !== $user->notifications) {
            $keyboard[] = [
                $this->buildActionButton(
                    self::NOTIFICATIONS,
                    AdminCallbackData::create(AdminCallbackAction::UserNotifications)->withUserId($user->telegramUserId),
                ),
            ];
        }

        $keyboard[] = $this->backButtonRow(
            AdminCallbackData::create(AdminCallbackAction::UsersList)->withPage(1),
        );

        return $keyboard;
    }

    /** @return ?list<array{text: string, callback_data: string}> */
    private function buildRoleActionRow(int $telegramUserId, Role $role): ?array
    {
        if (Role::Player === $role) {
            return [
                $this->buildActionButton(
                    self::PROMOTE_TO_ADMIN,
                    AdminCallbackData::create(AdminCallbackAction::PromoteUser)->withUserId($telegramUserId),
                    InlineButtonStyle::DANGER,
                ),
            ];
        }

        if (Role::Admin === $role) {
            return [
                $this->buildActionButton(
                    self::DEMOTE_TO_PLAYER,
                    AdminCallbackData::create(AdminCallbackAction::DemoteUser)->withUserId($telegramUserId),
                ),
            ];
        }

        return null;
    }

    public function buildUserNotFound(): TelegramMessage
    {
        $text = $this->formatHeader(self::HEADER_MESSAGE)
            . $this->formatter->newLine() . $this->formatter->escape('User not found');

        return $this->buildMessage(
            $text,
            [$this->backButtonRow(AdminCallbackData::create(AdminCallbackAction::UsersList)->withPage(1))],
        );
    }
}
