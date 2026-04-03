<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Telegram\TelegramUpdate;
use TelegramBot\Api\BotApi;

abstract class AbstractUpdateProcessor
{
    public function __construct(
        protected readonly BotApi $bot,
    ) {
    }

    abstract public function process(TelegramUpdate $update): void;
}