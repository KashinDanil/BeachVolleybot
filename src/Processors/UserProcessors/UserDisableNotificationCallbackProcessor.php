<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Telegram\MessageBuilders\NotificationSettingsMessageBuilder;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\User\UserRecord;

class UserDisableNotificationCallbackProcessor extends AbstractUserNotificationSwitchCallbackProcessor
{
    protected function applyNotificationChange(
        UserManager $userManager,
        UserRecord $user,
        NotificationType $notificationType,
    ): NotificationSettings {
        return $userManager->disableNotification($user, $notificationType);
    }

    protected function getConfirmationToastText(): string
    {
        return NotificationSettingsMessageBuilder::DISABLED_TOAST;
    }

    protected function getLogAction(): string
    {
        return 'notification_disabled';
    }
}
