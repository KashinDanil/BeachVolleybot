<?php

declare(strict_types=1);

namespace BeachVolleybot;

use TelegramBot\Api\BotApi;

readonly class App
{
    public function __construct(
        private BotApi $bot,
    ) {
    }

    public function run(): void
    {
        echo 'Hello, Beach Volleybot!';
    }
}
