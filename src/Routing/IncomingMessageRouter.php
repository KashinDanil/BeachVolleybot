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

    public function route(TelegramUpdate $update): void
    {
        if ($update->hasInlineQuery()) {
            new InlineQueryProcessor($this->telegramSender)->process($update);

            return;
        }

        $this->queueRouter->route($update);
    }
}
