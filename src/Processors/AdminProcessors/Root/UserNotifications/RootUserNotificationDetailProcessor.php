<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors\Root\UserNotifications;

use BeachVolleybot\Telegram\MessageBuilders\Admin\UserNotificationSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\UserRecord;

class RootUserNotificationDetailProcessor extends AbstractRootUserNotificationsProcessor
{
    protected function processUser(TelegramUpdate $update, UserRecord $user, NotificationSettings $notifications): void
    {
        $notificationType = $this->adminCallbackData->getNotificationType();

        if (null === $notificationType) {
            $this->answerCallbackQuery($update->callbackQuery, '');

            return;
        }

        $detailMessage = new UserNotificationSettingsMessageBuilder($user)->buildDetail($notificationType, $notifications);

        $this->editAdminPanelMessage($update->callbackQuery, $detailMessage);
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
