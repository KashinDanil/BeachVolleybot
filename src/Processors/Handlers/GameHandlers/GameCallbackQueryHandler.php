<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Processors\Handlers\Traits\CallbackProcessorResolverTrait;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

final readonly class GameCallbackQueryHandler extends AbstractGameQueueHandler
{
    use CallbackProcessorResolverTrait;

    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasCallbackQuery() && $update->callbackQuery->isInline();
    }

    protected function resolveGameId(TelegramUpdate $update): ?int
    {
        return new GameManager()->resolveGameIdByInlineMessageId($update->callbackQuery->inlineMessageId);
    }

    protected function getCallbackDataClass(): string
    {
        return GameCallbackData::class;
    }
}
