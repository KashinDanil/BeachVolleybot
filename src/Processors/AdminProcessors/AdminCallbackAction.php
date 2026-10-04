<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminAddNetProcessor;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminAddSlotProcessor;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminAddVolleyballProcessor;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminRemoveLocationCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminRemoveNetProcessor;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminRemoveSlotProcessor;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminRemoveVolleyballProcessor;
use BeachVolleybot\Processors\AdminProcessors\Games\AdminGameDetailCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Games\AdminGamesListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Games\Users\AdminUserSettingsProcessor;
use BeachVolleybot\Processors\AdminProcessors\Games\Users\AdminUsersListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\Log\RootLogClearCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\Log\RootLogFileActionsCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\Log\RootLogGetCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\Log\RootLogsListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\Log\RootLogTailCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserNotifications\RootDisableUserNotificationProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserNotifications\RootEnableUserNotificationProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserNotifications\RootUserNotificationDetailProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserNotifications\RootUserNotificationsListProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserRole\RootDemoteUserProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserRole\RootPromoteUserProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserRole\RootUserRoleDetailProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserRole\RootUserRoleListProcessor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\CallbackData\CallbackActionInterface;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserRecord;

enum AdminCallbackAction: string implements CallbackActionInterface
{
    case Settings = 'st';
    case Logs = 'lgs';
    case LogFile = 'lf';
    case LogGet = 'lg';
    case LogTail = 'lt';
    case LogClear = 'lc';
    case GamesList = 'gl';
    case GameDetail = 'gd';
    case GameUsers = 'gp';
    case UserSettings = 'ps';
    case UsersList = 'ul';
    case UserDetail = 'uv';
    case PromoteUser = 'pu';
    case DemoteUser = 'du';
    case UserNotifications = 'nl';
    case UserNotificationDetail = 'nd';
    case EnableUserNotification = 'ne';
    case DisableUserNotification = 'nx';
    case RemoveSlot = 'rs';
    case AddSlot = 'as';
    case RemoveLocation = 'rl';
    case AddNet = 'an';
    case RemoveNet = 'rn';
    case AddVolleyball = 'av';
    case RemoveVolleyball = 'rv';

    /**
     * @param TelegramMessageSender $telegramSender
     * @param AdminCallbackData $callbackData
     * @param UserRecord $sender
     *
     * @return AbstractActionProcessor
     */
    public function resolveProcessor(
        TelegramMessageSender $telegramSender,
        ?CallbackDataInterface $callbackData,
        UserRecord $sender,
    ): AbstractActionProcessor {
        return match ($this) {
            self::Settings => new SettingsMenuCallbackProcessor($telegramSender, $callbackData, $sender),
            self::Logs => new RootLogsListCallbackProcessor($telegramSender, $callbackData, $sender),
            self::LogFile => new RootLogFileActionsCallbackProcessor($telegramSender, $callbackData, $sender),
            self::LogGet => new RootLogGetCallbackProcessor($telegramSender, $callbackData, $sender),
            self::LogTail => new RootLogTailCallbackProcessor($telegramSender, $callbackData, $sender),
            self::LogClear => new RootLogClearCallbackProcessor($telegramSender, $callbackData, $sender),
            self::GamesList => new AdminGamesListCallbackProcessor($telegramSender, $callbackData, $sender),
            self::GameDetail => new AdminGameDetailCallbackProcessor($telegramSender, $callbackData, $sender),
            self::GameUsers => new AdminUsersListCallbackProcessor($telegramSender, $callbackData, $sender),
            self::UserSettings => new AdminUserSettingsProcessor($telegramSender, $callbackData, $sender),
            self::UsersList => new RootUserRoleListProcessor($telegramSender, $callbackData, $sender),
            self::UserDetail => new RootUserRoleDetailProcessor($telegramSender, $callbackData, $sender),
            self::PromoteUser => new RootPromoteUserProcessor($telegramSender, $callbackData, $sender),
            self::DemoteUser => new RootDemoteUserProcessor($telegramSender, $callbackData, $sender),
            self::UserNotifications => new RootUserNotificationsListProcessor($telegramSender, $callbackData, $sender),
            self::UserNotificationDetail => new RootUserNotificationDetailProcessor($telegramSender, $callbackData, $sender),
            self::EnableUserNotification => new RootEnableUserNotificationProcessor($telegramSender, $callbackData, $sender),
            self::DisableUserNotification => new RootDisableUserNotificationProcessor($telegramSender, $callbackData, $sender),
            self::RemoveSlot => new AdminRemoveSlotProcessor($telegramSender, $callbackData, $sender),
            self::AddSlot => new AdminAddSlotProcessor($telegramSender, $callbackData, $sender),
            self::RemoveLocation => new AdminRemoveLocationCallbackProcessor($telegramSender, $callbackData, $sender),
            self::AddNet => new AdminAddNetProcessor($telegramSender, $callbackData, $sender),
            self::RemoveNet => new AdminRemoveNetProcessor($telegramSender, $callbackData, $sender),
            self::AddVolleyball => new AdminAddVolleyballProcessor($telegramSender, $callbackData, $sender),
            self::RemoveVolleyball => new AdminRemoveVolleyballProcessor($telegramSender, $callbackData, $sender),
        };
    }

    public function requiredRole(): Role
    {
        return match ($this) {
            self::Logs,
            self::LogFile,
            self::LogGet,
            self::LogTail,
            self::LogClear,
            self::UsersList,
            self::UserDetail,
            self::PromoteUser,
            self::DemoteUser,
            self::UserNotifications,
            self::UserNotificationDetail,
            self::EnableUserNotification,
            self::DisableUserNotification,
            self::GameUsers,
            self::UserSettings,
            self::RemoveSlot,
            self::AddSlot,
            self::RemoveLocation,
            self::AddNet,
            self::RemoveNet,
            self::AddVolleyball,
            self::RemoveVolleyball => Role::Root,
            default => Role::Admin,
        };
    }
}
