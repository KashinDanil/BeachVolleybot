<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\CallbackData\CallbackActionInterface;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\Role;

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
     *
     * @return AbstractActionProcessor
     */
    public function resolveProcessor(
        TelegramMessageSender $telegramSender,
        ?CallbackDataInterface $callbackData
    ): AbstractActionProcessor {
        return match ($this) {
            self::Settings => new SettingsMenuCallbackProcessor($telegramSender, $callbackData),
            self::Logs => new RootLogsListCallbackProcessor($telegramSender, $callbackData),
            self::LogFile => new RootLogFileActionsCallbackProcessor($telegramSender, $callbackData),
            self::LogGet => new RootLogGetCallbackProcessor($telegramSender, $callbackData),
            self::LogTail => new RootLogTailCallbackProcessor($telegramSender, $callbackData),
            self::LogClear => new RootLogClearCallbackProcessor($telegramSender, $callbackData),
            self::GamesList => new AdminGamesListCallbackProcessor($telegramSender, $callbackData),
            self::GameDetail => new AdminGameDetailCallbackProcessor($telegramSender, $callbackData),
            self::GameUsers => new AdminUsersListCallbackProcessor($telegramSender, $callbackData),
            self::UserSettings => new AdminUserSettingsProcessor($telegramSender, $callbackData),
            self::UsersList => new RootUserRoleListProcessor($telegramSender, $callbackData),
            self::UserDetail => new RootUserRoleDetailProcessor($telegramSender, $callbackData),
            self::PromoteUser => new RootPromoteUserProcessor($telegramSender, $callbackData),
            self::DemoteUser => new RootDemoteUserProcessor($telegramSender, $callbackData),
            self::RemoveSlot => new AdminRemoveSlotProcessor($telegramSender, $callbackData),
            self::AddSlot => new AdminAddSlotProcessor($telegramSender, $callbackData),
            self::RemoveLocation => new AdminRemoveLocationCallbackProcessor($telegramSender, $callbackData),
            self::AddNet => new AdminAddNetProcessor($telegramSender, $callbackData),
            self::RemoveNet => new AdminRemoveNetProcessor($telegramSender, $callbackData),
            self::AddVolleyball => new AdminAddVolleyballProcessor($telegramSender, $callbackData),
            self::RemoveVolleyball => new AdminRemoveVolleyballProcessor($telegramSender, $callbackData),
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
            self::DemoteUser => Role::Root,
            default => Role::Admin,
        };
    }
}
