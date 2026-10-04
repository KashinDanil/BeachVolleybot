<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors\Root\UserNotifications;

use BeachVolleybot\Telegram\MessageBuilders\Admin\UserNotificationSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\UserRecord;

class RootUserNotificationsListProcessor extends AbstractRootUserNotificationsProcessor
{
    protected function processUser(TelegramUpdate $update, UserRecord $user, NotificationSettings $notifications): void
    {
        $listMessage = new UserNotificationSettingsMessageBuilder($user)->buildList($notifications);

        $this->editAdminPanelMessage($update->callbackQuery, $listMessage);
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
