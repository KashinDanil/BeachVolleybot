<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors\Root\UserNotifications;

use BeachVolleybot\Telegram\MessageBuilders\AbstractNotificationSettingsMessageBuilder;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\User\UserRecord;

class RootEnableUserNotificationProcessor extends AbstractRootUserNotificationSwitchProcessor
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
        return AbstractNotificationSettingsMessageBuilder::ENABLED_TOAST;
    }

    protected function getLogAction(): string
    {
        return 'root_enable_user_notification';
    }
}
