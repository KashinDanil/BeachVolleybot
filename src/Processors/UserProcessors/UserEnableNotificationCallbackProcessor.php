<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Telegram\MessageBuilders\NotificationsMessageBuilder;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\User\UserRecord;

class UserEnableNotificationCallbackProcessor extends AbstractUserNotificationSwitchCallbackProcessor
{
    protected function applyNotificationChange(
        UserManager $userManager,
        UserRecord $user,
        NotificationType $notificationType,
    ): NotificationSettings {
        return $userManager->enableNotification($user, $notificationType);
    }

    protected function getConfirmationToastText(): string
    {
        return NotificationsMessageBuilder::ENABLED_TOAST;
    }

    protected function getLogAction(): string
    {
        return 'notification_enabled';
    }
}
