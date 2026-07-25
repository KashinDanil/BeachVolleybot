<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\InlineHandlers;

use BeachVolleybot\Common\Extractors\ForwardGameQueryExtractor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\ForwardGameProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class ForwardGameHandler extends AbstractChosenInlineResultHandler
{
    protected function matchesQuery(string $query): bool
    {
        return null !== ForwardGameQueryExtractor::extract($query);
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new ForwardGameProcessor($telegramSender);
    }
}
