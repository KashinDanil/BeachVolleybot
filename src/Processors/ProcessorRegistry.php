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
        $handler = $this->findHandler($update);

        if (!$handler instanceof AbstractQueuedProcessorHandler) {
            return null;
        }

        return $handler->routeToQueue($update);
    }

    public function resolveProcessor(
        TelegramUpdate $update,
        TelegramMessageSender $telegramSender,
    ): ?AbstractActionProcessor {
        return $this->findHandler($update)?->createProcessor($telegramSender, $update);
    }

    private function findHandler(TelegramUpdate $update): ?AbstractProcessorHandler
    {
        return array_find($this->handlers, static fn($handler) => $handler->matches($update));
    }
}
