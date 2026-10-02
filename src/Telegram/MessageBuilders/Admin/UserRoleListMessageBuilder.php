<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Admin;

use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\KeyboardPagination;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserRecord;

final class UserRoleListMessageBuilder extends AbstractAdminMessageBuilder
{
    public const string  HEADER_MESSAGE = 'Users';
    private const string NO_USERS_FOUND = 'No users found';

    /**
     * @param list<UserRecord> $users
     */
    public function build(array $users, KeyboardPagination $pagination): TelegramMessage
    {
        return $this->buildMessage(
            $this->buildUsersListText($users, $pagination),
            $this->buildUsersListKeyboard($users, $pagination),
        );
    }

    /**
     * @param list<UserRecord> $users
     */
    private function buildUsersListText(array $users, KeyboardPagination $pagination): string
    {
        $header = $this->formatHeader(self::HEADER_MESSAGE);

        if (empty($users)) {
            return $header . $this->formatter->newLine() . $this->formatter->escape(self::NO_USERS_FOUND);
        }

        return $header . $this->formatter->newLine() . $this->formatter->escape("Page {$pagination->getPage()} of {$pagination->getTotalPages()}");
    }

    /**
     * @param list<UserRecord> $users
     */
    private function buildUsersListKeyboard(array $users, KeyboardPagination $pagination): array
    {
        $keyboard = [];

        foreach ($users as $user) {
            $keyboard[] = [$this->buildUserButton($user)];
        }

        $paginationRow = $this->paginationRow($pagination, AdminCallbackData::create(AdminCallbackAction::UsersList));
        if (null !== $paginationRow) {
            $keyboard[] = $paginationRow;
        }

        $keyboard[] = $this->backButtonRow(AdminCallbackData::create(AdminCallbackAction::Settings));

        return $keyboard;
    }

    private function buildUserButton(UserRecord $user): array
    {
        $name = Player::buildName($user->firstName, $user->lastName);

        return $this->buildActionButton(
            "$name — {$user->role->name}",
            AdminCallbackData::create(AdminCallbackAction::UserDetail)->withUserId($user->telegramUserId),
            $this->styleForRole($user->role),
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
