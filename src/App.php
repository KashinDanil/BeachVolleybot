<?php

declare(strict_types=1);

namespace BeachVolleybot;

use TelegramBot\Api\BotApi;

class App
{
    public function __construct(
        private readonly BotApi $bot,
        private readonly array $incommingMessage,
    ) {
    }

    public function run(): void
    {
        echo 'Hello, Beach Volleybot!';
    }
}
