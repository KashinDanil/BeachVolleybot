<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

readonly class ProcessorRegistry
{
    /**
     * @param AbstractProcessorHandler[] $handlers
     */
    public function __construct(private array $handlers)
    {
    }

    public function resolveQueueName(TelegramUpdate $update): ?string
    {
        foreach ($this->handlers as $handler) {
            if ($handler->matches($update)) {
                return $handler->routeToQueue($update);
            }
        }

        return null;
    }

    public function resolveProcessor(
        TelegramUpdate $update,
        TelegramMessageSender $telegramSender,
    ): ?AbstractActionProcessor {
        foreach ($this->handlers as $handler) {
            if ($handler->matches($update)) {
                return $handler->createProcessor($telegramSender, $update);
            }
        }

        return null;
    }
}
