<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors\Root\UserRole;

use BeachVolleybot\Processors\AdminProcessors\AbstractAdminMutationProcessor;
use BeachVolleybot\Telegram\MessageBuilders\Factories\UserRoleDetailMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserManager;

abstract class AbstractRootUserRoleMutationProcessor extends AbstractAdminMutationProcessor
{
    abstract protected function sourceRole(): Role;

    abstract protected function targetRole(): Role;

    abstract protected function logAction(): string;

    abstract protected function successToast(): string;

    public function process(TelegramUpdate $update): void
    {
        $telegramUserId = $this->adminCallbackData->getUserId();
        $userManager = new UserManager();
        $user = $userManager->findUserRecordById($telegramUserId);

        if (null === $user) {
            $this->refreshDetail($update, $telegramUserId, 'User not found');

            return;
        }

        if ($user->role->isRoot()) {
            $this->refreshDetail($update, $telegramUserId, 'Cannot change Root');

            return;
        }

        if ($this->sourceRole() !== $user->role) {
            $this->refreshDetail($update, $telegramUserId, '');

            return;
        }

        $userManager->changeRole($user, $this->targetRole());
        $this->logAdminAction($update->callbackQuery->from, $this->logAction(), "userId=$telegramUserId");
        $this->refreshDetail($update, $telegramUserId, $this->successToast());
    }

    private function refreshDetail(TelegramUpdate $update, int $telegramUserId, string $toast): void
    {
        $this->editSettingsMessage($update->callbackQuery, UserRoleDetailMessageFactory::build($telegramUserId));
        $this->answerCallbackQuery($update->callbackQuery, $toast);
    }
}
