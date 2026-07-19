<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Telegram\MessageBuilders\Factories\UserRoleDetailMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\Role;

abstract class AbstractRootUserRoleMutationProcessor extends AbstractAdminMutationProcessor
{
    abstract protected function sourceRole(): Role;

    abstract protected function targetRole(): Role;

    abstract protected function logAction(): string;

    abstract protected function successToast(): string;

    public function process(TelegramUpdate $update): void
    {
        $telegramUserId = $this->adminCallbackData->getUserId();
        $userRepository = new UserRepository(Connection::get());
        $roleId = $userRepository->findRoleById($telegramUserId);

        if (null === $roleId) {
            $this->refreshDetail($update, $telegramUserId, 'User not found');

            return;
        }

        $currentRole = Role::tryFrom($roleId) ?? Role::Player;

        if ($currentRole->isRoot()) {
            $this->refreshDetail($update, $telegramUserId, 'Cannot change Root');

            return;
        }

        if ($this->sourceRole() !== $currentRole) {
            $this->refreshDetail($update, $telegramUserId, '');

            return;
        }

        $userRepository->updateRole($telegramUserId, $this->targetRole());
        $this->logAdminAction($update->callbackQuery->from, $this->logAction(), "userId=$telegramUserId");
        $this->refreshDetail($update, $telegramUserId, $this->successToast());
    }

    private function refreshDetail(TelegramUpdate $update, int $telegramUserId, string $toast): void
    {
        $this->editSettingsMessage($update->callbackQuery, UserRoleDetailMessageFactory::build($telegramUserId));
        $this->answerCallbackQuery($update->callbackQuery, $toast);
    }
}
