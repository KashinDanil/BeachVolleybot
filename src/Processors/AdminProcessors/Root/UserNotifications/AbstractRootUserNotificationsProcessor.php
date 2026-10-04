<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors\Root\UserNotifications;

use BeachVolleybot\Processors\AdminProcessors\AbstractAdminMutationProcessor;
use BeachVolleybot\Telegram\MessageBuilders\Factories\UserRoleDetailMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\User\UserRecord;

abstract class AbstractRootUserNotificationsProcessor extends AbstractAdminMutationProcessor
{
    public const string UNAVAILABLE_TOAST = 'Notifications unavailable';

    public function process(TelegramUpdate $update): void
    {
        $telegramUserId = $this->adminCallbackData->getUserId();
        $user = new UserManager()->findUserRecordById($telegramUserId);

        if (null === $user?->notifications) {
            $this->editAdminPanelMessage($update->callbackQuery, UserRoleDetailMessageFactory::build($telegramUserId));
            $this->answerCallbackQuery($update->callbackQuery, self::UNAVAILABLE_TOAST);

            return;
        }

        $this->processUser($update, $user, $user->notifications);
    }

    abstract protected function processUser(
        TelegramUpdate $update,
        UserRecord $user,
        NotificationSettings $notifications,
    ): void;
}
