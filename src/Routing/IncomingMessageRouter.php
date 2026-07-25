<?php

declare(strict_types=1);

namespace BeachVolleybot\Routing;

use BeachVolleybot\Processors\ProcessorRegistry;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

readonly class IncomingMessageRouter
{
    public function __construct(
        private TelegramMessageSender $telegramSender,
        private ProcessorRegistry $immediateRegistry,
        private IncomingMessageQueueRouter $queueRouter,
    ) {
    }

    public function route(TelegramUpdate $update): void
    {
        $processor = $this->immediateRegistry->resolveProcessor($update, $this->telegramSender);

        if (null !== $processor) {
            $processor->process($update);

            return;
        }

        $this->queueRouter->route($update);
    }
}
