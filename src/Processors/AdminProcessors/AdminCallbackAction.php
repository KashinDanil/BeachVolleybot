<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\TelegramMessageSender;

enum AdminCallbackAction: string
{
    case Settings = 'st';
    case Logs = 'lgs';
    case LogFile = 'lf';
    case LogGet = 'lg';
    case LogTail = 'lt';
    case LogClear = 'lc';
    case GamesList = 'gl';
    case GameDetail = 'gd';
    case GamePlayers = 'gp';
    case PlayerSettings = 'ps';
    case RemoveSlot = 'rs';
    case RemoveLocation = 'rl';
    case AddNet = 'an';
    case RemoveNet = 'rn';
    case AddVolleyball = 'av';
    case RemoveVolleyball = 'rv';

    public function resolveProcessor(TelegramMessageSender $telegramSender, AdminCallbackData $adminCallbackData): AbstractActionProcessor
    {
        return match ($this) {
            self::Settings => new SettingsMenuCallbackProcessor($telegramSender, $adminCallbackData),
            self::Logs => new LogsListCallbackProcessor($telegramSender, $adminCallbackData),
            self::LogFile => new LogFileActionsCallbackProcessor($telegramSender, $adminCallbackData),
            self::LogGet => new LogGetCallbackProcessor($telegramSender, $adminCallbackData),
            self::LogTail => new LogTailCallbackProcessor($telegramSender, $adminCallbackData),
            self::LogClear => new LogClearCallbackProcessor($telegramSender, $adminCallbackData),
            self::GamesList => new AdminGamesListCallbackProcessor($telegramSender, $adminCallbackData),
            self::GameDetail => new AdminGameDetailCallbackProcessor($telegramSender, $adminCallbackData),
            self::GamePlayers => new AdminPlayersListCallbackProcessor($telegramSender, $adminCallbackData),
            self::PlayerSettings => new AdminPlayerSettingsProcessor($telegramSender, $adminCallbackData),
            self::RemoveSlot => new AdminRemoveSlotProcessor($telegramSender, $adminCallbackData),
            self::RemoveLocation => new AdminRemoveLocationCallbackProcessor($telegramSender, $adminCallbackData),
            self::AddNet => new AdminAddNetProcessor($telegramSender, $adminCallbackData),
            self::RemoveNet => new AdminRemoveNetProcessor($telegramSender, $adminCallbackData),
            self::AddVolleyball => new AdminAddVolleyballProcessor($telegramSender, $adminCallbackData),
            self::RemoveVolleyball => new AdminRemoveVolleyballProcessor($telegramSender, $adminCallbackData),
        };
    }
}
