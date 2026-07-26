<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\NewGameHandlers;

use BeachVolleybot\Processors\Handlers\PrivateHandlers\AbstractDmQueueHandler;
use BeachVolleybot\Processors\Handlers\Traits\CallbackProcessorResolverTrait;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

final readonly class NewGameCallbackQueryHandler extends AbstractDmQueueHandler
{
    use CallbackProcessorResolverTrait;

    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasCallbackQuery()
            && $update->callbackQuery->hasMessage()
            && null !== NewGameCallbackData::fromJson($update->callbackQuery->data);
    }

    protected function getCallbackDataClass(): string
    {
        return NewGameCallbackData::class;
    }
}
