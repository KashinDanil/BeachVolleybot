<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\InlineHandlers;

use BeachVolleybot\Processors\AbstractProcessorHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract readonly class AbstractChosenInlineResultHandler extends AbstractProcessorHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasChosenInlineResult()
            && null !== $update->chosenInlineResult->inlineMessageId
            && $this->matchesQuery($update->chosenInlineResult->query);
    }

    abstract protected function matchesQuery(string $query): bool;
}
