<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGameDatePageProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGamePickDateProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGamePickTimeProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGamePickVenueProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGameSendProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGameTimePageProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGameVenuePageProcessor;
use BeachVolleybot\Telegram\CallbackData\CallbackActionInterface;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\TelegramMessageSender;

enum NewGameCallbackAction: string implements CallbackActionInterface
{
    case ShowDatePage  = 'dp';
    case PickDate      = 'd';
    case ShowTimePage  = 'tp';
    case PickTime      = 't';
    case ShowVenuePage = 'vp';
    case PickVenue     = 'v';
    case SkipVenue     = 'vs';
    case Send          = 's';

    /**
     * @param TelegramMessageSender $telegramSender
     * @param NewGameCallbackData $callbackData
     *
     * @return AbstractActionProcessor
     */
    public function resolveProcessor(
        TelegramMessageSender $telegramSender,
        ?CallbackDataInterface $callbackData,
    ): AbstractActionProcessor {
        return match ($this) {
            self::ShowDatePage  => new NewGameDatePageProcessor($telegramSender, $callbackData),
            self::PickDate      => new NewGamePickDateProcessor($telegramSender, $callbackData),
            self::ShowTimePage  => new NewGameTimePageProcessor($telegramSender, $callbackData),
            self::PickTime      => new NewGamePickTimeProcessor($telegramSender, $callbackData),
            self::ShowVenuePage => new NewGameVenuePageProcessor($telegramSender, $callbackData),
            self::PickVenue, self::SkipVenue => new NewGamePickVenueProcessor($telegramSender, $callbackData),
            self::Send => new NewGameSendProcessor($telegramSender, $callbackData),
        };
    }
}
