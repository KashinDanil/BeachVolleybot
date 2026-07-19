<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\Role;

final class UserRoleDetailMessageBuilder extends AbstractAdminMessageBuilder
{
    private const string HEADER_MESSAGE   = 'User';
    private const string PROMOTE_TO_ADMIN = 'Promote to Admin';
    private const string DEMOTE_TO_PLAYER = 'Demote to Player';

    /**
     * @param array<string, mixed> $userRow
     */
    public function buildUserDetail(array $userRow): TelegramMessage
    {
        $telegramUserId = (int)$userRow['telegram_user_id'];
        $userName = User::buildName($userRow['first_name'], $userRow['last_name'] ?? null);
        $userLink = User::buildLink($userRow['username'] ?? null);
        $username = $userRow['username'] ?? null;
        $role = Role::tryFrom((int)$userRow['role']) ?? Role::Player;

        return $this->buildMessage(
            $this->buildUserDetailText($telegramUserId, $userName, $userLink, $username, $role),
            $this->buildUserDetailKeyboard($telegramUserId, $role),
        );
    }

    private function buildUserDetailText(
        int $telegramUserId,
        string $userName,
        ?string $userLink,
        ?string $username,
        Role $role,
    ): string {
        $namePart = null !== $userLink
            ? $this->formatter->link($userName, $userLink)
            : $this->formatter->escape($userName);

        return implode($this->formatter->newLine(), [
            $this->formatHeader(self::HEADER_MESSAGE),
            $namePart,
            $this->formatter->escape('Username: ' . (null !== $username ? "@$username" : '—')),
            $this->formatter->escape("Telegram ID: ") . $this->formatter->code((string)$telegramUserId),
            $this->formatter->escape("Role: ") . $this->formatter->bold($role->name),
        ]);
    }

    private function buildUserDetailKeyboard(int $telegramUserId, Role $role): array
    {
        $keyboard = [];

        $roleActionRow = $this->buildRoleActionRow($telegramUserId, $role);
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
