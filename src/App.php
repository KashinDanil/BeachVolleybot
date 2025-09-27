<?php

declare(strict_types=1);

namespace BeachVolleybot;

use BeachVolleybot\Webhook\IncomingMessageDTO;
use TelegramBot\Api\BotApi;

readonly class App
{
    public function __construct(
        private BotApi $bot,
        private IncomingMessageDTO $incomingMessage,
    ) {
    }

    public function run(): void
    {
        echo 'Hello, Beach Volleybot!';
    }
}
