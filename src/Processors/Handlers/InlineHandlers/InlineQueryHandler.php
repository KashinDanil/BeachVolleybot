<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\InlineHandlers;

use BeachVolleybot\Processors\AbstractProcessorHandler;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\InlineQueryProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class InlineQueryHandler extends AbstractProcessorHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasInlineQuery();
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new InlineQueryProcessor($telegramSender);
    }
}
