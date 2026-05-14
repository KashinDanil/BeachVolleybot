<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

abstract readonly class AbstractProcessorHandler
{
    abstract public function matches(TelegramUpdate $update): bool;

    abstract public function routeToQueue(TelegramUpdate $update): ?string;

    abstract public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor;
}
