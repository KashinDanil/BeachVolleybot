<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Telegram\Incoming\TelegramUpdate;
use TelegramBot\Api\BotApi;

abstract class AbstractActionProcessor
{
    public function __construct(
        protected readonly BotApi $bot,
    ) {
    }

    abstract public function process(TelegramUpdate $update): void;
}