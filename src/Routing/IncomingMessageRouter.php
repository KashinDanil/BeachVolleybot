<?php

declare(strict_types=1);

namespace BeachVolleybot\Routing;

use BeachVolleybot\Processors\UpdateProcessors\InlineQueryProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

readonly class IncomingMessageRouter
{
    public function __construct(
        private TelegramMessageSender $telegramSender,
        private IncomingMessageQueueRouter $queueRouter,
    ) {
    }

    public function route(array $payload): void
    {
        if (isset($payload['inline_query'])) {
            $this->processInlineQuery($payload);

            return;
        }

        $this->queueRouter->route($payload);
    }

    private function processInlineQuery(array $payload): void
    {
        $processor = new InlineQueryProcessor($this->telegramSender);
        $processor->process(TelegramUpdate::fromArray($payload));
    }
}
