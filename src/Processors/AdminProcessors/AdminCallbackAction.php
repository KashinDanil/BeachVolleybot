<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\CallbackData\CallbackActionInterface;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\TelegramMessageSender;

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
    case GamePlayers = 'gp';
    case PlayerSettings = 'ps';
    case RemoveSlot = 'rs';
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
            self::Logs => new LogsListCallbackProcessor($telegramSender, $callbackData),
            self::LogFile => new LogFileActionsCallbackProcessor($telegramSender, $callbackData),
            self::LogGet => new LogGetCallbackProcessor($telegramSender, $callbackData),
            self::LogTail => new LogTailCallbackProcessor($telegramSender, $callbackData),
            self::LogClear => new LogClearCallbackProcessor($telegramSender, $callbackData),
            self::GamesList => new AdminGamesListCallbackProcessor($telegramSender, $callbackData),
            self::GameDetail => new AdminGameDetailCallbackProcessor($telegramSender, $callbackData),
            self::GamePlayers => new AdminPlayersListCallbackProcessor($telegramSender, $callbackData),
            self::PlayerSettings => new AdminPlayerSettingsProcessor($telegramSender, $callbackData),
            self::RemoveSlot => new AdminRemoveSlotProcessor($telegramSender, $callbackData),
            self::RemoveLocation => new AdminRemoveLocationCallbackProcessor($telegramSender, $callbackData),
            self::AddNet => new AdminAddNetProcessor($telegramSender, $callbackData),
            self::RemoveNet => new AdminRemoveNetProcessor($telegramSender, $callbackData),
            self::AddVolleyball => new AdminAddVolleyballProcessor($telegramSender, $callbackData),
            self::RemoveVolleyball => new AdminRemoveVolleyballProcessor($telegramSender, $callbackData),
        };
    }
}
