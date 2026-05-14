<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\Handlers\Traits\CallbackProcessorResolverTrait;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

final readonly class UserCallbackQueryHandler extends AbstractDmQueueHandler
{
    use CallbackProcessorResolverTrait;

    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasCallbackQuery()
            && !$update->callbackQuery->isInline()
            && null !== UserCallbackData::fromJson($update->callbackQuery->data);
    }

    protected function getCallbackDataClass(): string
    {
        return UserCallbackData::class;
    }
}
