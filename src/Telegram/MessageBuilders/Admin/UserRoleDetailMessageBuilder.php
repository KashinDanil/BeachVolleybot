<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Admin;

use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserRecord;

final class UserRoleDetailMessageBuilder extends AbstractAdminMessageBuilder
{
    private const string HEADER_MESSAGE   = 'User';
    private const string PROMOTE_TO_ADMIN = 'Promote to Admin';
    private const string DEMOTE_TO_PLAYER = 'Demote to Player';

    public function buildUserDetail(UserRecord $user): TelegramMessage
    {
        return $this->buildMessage(
            $this->buildUserDetailText($user),
            $this->buildUserDetailKeyboard($user),
        );
    }

    private function buildUserDetailText(UserRecord $user): string
    {
        $userName = Player::buildName($user->firstName, $user->lastName);
        $userLink = Player::buildLink($user->username);
        $namePart = null !== $userLink
            ? $this->formatter->link($userName, $userLink)
            : $this->formatter->escape($userName);

        return implode($this->formatter->newLine(), [
            $this->formatHeader(self::HEADER_MESSAGE),
            $namePart,
            $this->formatter->escape('Username: ' . (null !== $user->username ? "@$user->username" : '—')),
            $this->formatter->escape("Telegram ID: ") . $this->formatter->code((string)$user->telegramUserId),
            $this->formatter->escape("Role: ") . $this->formatter->bold($user->role->name),
        ]);
    }

    private function buildUserDetailKeyboard(UserRecord $user): array
    {
        $keyboard = [];

        $roleActionRow = $this->buildRoleActionRow($user->telegramUserId, $user->role);
        if (null !== $roleActionRow) {
            $keyboard[] = $roleActionRow;
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
