<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\InlineHandlers;

use BeachVolleybot\Common\Extractors\ForwardGameQueryExtractor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CreateGameProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class CreateGameHandler extends AbstractChosenInlineResultHandler
{
    protected function matchesQuery(string $query): bool
    {
        return null === ForwardGameQueryExtractor::extract($query);
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new CreateGameProcessor($telegramSender);
    }
}
