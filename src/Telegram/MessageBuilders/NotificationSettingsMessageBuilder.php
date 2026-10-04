<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\User\NotificationType;

final class NotificationSettingsMessageBuilder extends AbstractNotificationSettingsMessageBuilder
{
    protected function listCallbackData(): CallbackDataInterface
    {
        return UserCallbackData::create(UserCallbackAction::NotificationsList);
    }

    protected function detailCallbackData(NotificationType $type): CallbackDataInterface
    {
        return UserCallbackData::create(UserCallbackAction::NotificationDetail)->withNotificationType($type);
    }

    protected function enableCallbackData(NotificationType $type): CallbackDataInterface
    {
        return UserCallbackData::create(UserCallbackAction::EnableNotification)->withNotificationType($type);
    }

    protected function disableCallbackData(NotificationType $type): CallbackDataInterface
    {
        return UserCallbackData::create(UserCallbackAction::DisableNotification)->withNotificationType($type);
    }
}
