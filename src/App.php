<?php

declare(strict_types=1);

namespace BeachVolleybot;

use BeachVolleybot\Common\Logger;
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
        Logger::logApp(sprintf('Received message: %s' . PHP_EOL, json_encode($this->incomingMessage->getPayload())));
        echo 'Hello, Beach Volleybot!';
    }
}
