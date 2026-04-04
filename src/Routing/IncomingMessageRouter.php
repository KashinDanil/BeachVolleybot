<?php

declare(strict_types=1);

namespace BeachVolleybot\Routing;

use BeachVolleybot\Processors\InlineQueryProcessor;
use BeachVolleybot\Queue\IncomingMessageQueueRouter;
use BeachVolleybot\Telegram\Incoming\TelegramUpdate;
use TelegramBot\Api\BotApi;

readonly class IncomingMessageRouter
{
    public function __construct(
        private BotApi $bot,
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
        $processor = new InlineQueryProcessor($this->bot);
        $processor->process(TelegramUpdate::fromArray($payload));
    }


}