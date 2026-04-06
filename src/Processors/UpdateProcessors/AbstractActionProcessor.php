<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Telegram\InlineMessageRefresher;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use TelegramBot\Api\BotApi;

abstract class AbstractActionProcessor
{
    public function __construct(
        protected readonly BotApi $bot,
    ) {
    }

    abstract public function process(TelegramUpdate $update): void;

    protected function refreshInlineMessage(string $inlineMessageId): void
    {
        $refresher = new InlineMessageRefresher(new TelegramMessageSender($this->bot));
        $refresher->refresh($inlineMessageId);
    }
}
