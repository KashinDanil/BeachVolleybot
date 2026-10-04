<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors\Root\UserNotifications;

use BeachVolleybot\Telegram\MessageBuilders\AbstractNotificationSettingsMessageBuilder;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\User\UserRecord;

class RootDisableUserNotificationProcessor extends AbstractRootUserNotificationSwitchProcessor
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
        return AbstractNotificationSettingsMessageBuilder::DISABLED_TOAST;
    }

    protected function getLogAction(): string
    {
        return 'root_disable_user_notification';
    }
}
