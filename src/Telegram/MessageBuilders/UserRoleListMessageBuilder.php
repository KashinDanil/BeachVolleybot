<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\Role;

final class UserRoleListMessageBuilder extends AbstractAdminMessageBuilder
{
    public const string  HEADER_MESSAGE = 'Users';
    private const string NO_USERS_FOUND = 'No users found';

    /**
     * @param list<array<string, mixed>> $userRows
     */
    public function build(array $userRows, KeyboardPagination $pagination): TelegramMessage
    {
        return $this->buildMessage(
            $this->buildUsersListText($userRows, $pagination),
            $this->buildUsersListKeyboard($userRows, $pagination),
        );
    }

    /**
     * @param list<array<string, mixed>> $userRows
     */
    private function buildUsersListText(array $userRows, KeyboardPagination $pagination): string
    {
        $header = $this->formatHeader(self::HEADER_MESSAGE);

        if (empty($userRows)) {
            return $header . $this->formatter->newLine() . $this->formatter->escape(self::NO_USERS_FOUND);
        }

        return $header . $this->formatter->newLine() . $this->formatter->escape("Page {$pagination->getPage()} of {$pagination->getTotalPages()}");
    }

    /**
     * @param list<array<string, mixed>> $userRows
     */
    private function buildUsersListKeyboard(array $userRows, KeyboardPagination $pagination): array
    {
        $keyboard = [];

        foreach ($userRows as $userRow) {
            $keyboard[] = [$this->buildUserButton($userRow)];
        }

        $paginationRow = $this->paginationRow($pagination, AdminCallbackData::create(AdminCallbackAction::UsersList));
        if (null !== $paginationRow) {
            $keyboard[] = $paginationRow;
        }

        $keyboard[] = $this->backButtonRow(AdminCallbackData::create(AdminCallbackAction::Settings));

        return $keyboard;
    }

    /**
     * @param array<string, mixed> $userRow
     */
    private function buildUserButton(array $userRow): array
    {
        $telegramUserId = (int)$userRow['telegram_user_id'];
        $name = User::buildName($userRow['first_name'], $userRow['last_name'] ?? null);
        $role = Role::tryFrom((int)$userRow['role']) ?? Role::Player;

        return $this->buildActionButton(
            "$name — {$role->name}",
            AdminCallbackData::create(AdminCallbackAction::UserDetail)->withUserId($telegramUserId),
            $this->styleForRole($role),
        );
    }

    private function styleForRole(Role $role): ?InlineButtonStyle
    {
        return match ($role) {
            Role::Root => InlineButtonStyle::PRIMARY,
            Role::Admin => InlineButtonStyle::DANGER,
            Role::Player => null,
        };
    }
}
