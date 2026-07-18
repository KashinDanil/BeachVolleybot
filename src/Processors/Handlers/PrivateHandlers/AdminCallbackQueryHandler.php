<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\Handlers\Traits\CallbackProcessorResolverTrait;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\CurrentUser;

final readonly class AdminCallbackQueryHandler extends AbstractDmQueueHandler
{
    use CallbackProcessorResolverTrait;

    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasCallbackQuery()
            && !$update->callbackQuery->isInline()
            && null !== AdminCallbackData::fromJson($update->callbackQuery->data)
            && CurrentUser::fromTelegramId($update->callbackQuery->from->id)->isAdmin();
    }

    protected function getCallbackDataClass(): string
    {
        return AdminCallbackData::class;
    }
}
