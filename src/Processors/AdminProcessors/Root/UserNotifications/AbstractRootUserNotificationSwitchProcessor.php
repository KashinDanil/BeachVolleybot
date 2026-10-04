<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors\Root\UserNotifications;

use BeachVolleybot\Telegram\MessageBuilders\Admin\UserNotificationSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\User\UserRecord;

abstract class AbstractRootUserNotificationSwitchProcessor extends AbstractRootUserNotificationsProcessor
{
    protected function processUser(TelegramUpdate $update, UserRecord $user, NotificationSettings $notifications): void
    {
        $callbackQuery = $update->callbackQuery;
        $notificationType = $this->adminCallbackData->getNotificationType();

        if (null === $notificationType) {
            $this->answerCallbackQuery($callbackQuery, '');

            return;
        }

        $changedNotifications = $this->applyNotificationChange(new UserManager(), $user, $notificationType);

        $this->editSettingsMessage(
            $callbackQuery,
            new UserNotificationSettingsMessageBuilder($user)->buildDetail($notificationType, $changedNotifications),
        );
        $this->answerCallbackQuery($callbackQuery, $this->getConfirmationToastText());
        $this->logAdminAction(
            $callbackQuery->from,
            $this->getLogAction(),
            "userId=$user->telegramUserId notification=$notificationType->name",
        );
    }

    abstract protected function applyNotificationChange(
        UserManager $userManager,
        UserRecord $user,
        NotificationType $notificationType,
    ): NotificationSettings;

    abstract protected function getConfirmationToastText(): string;

    abstract protected function getLogAction(): string;
}
