<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use TelegramBot\Api\BotApi;

readonly class TelegramMessageSender
{
    public function __construct(
        private BotApi $bot,
    ) {
    }

    public function editInlineMessage(string $inlineMessageId, TelegramMessage $message): void
    {
        $this->bot->editMessageText(
            null,
            null,
            $message->getText()->getMessageText(),
            $message->getText()->getParseMode(),
            $message->getText()->isDisableWebPagePreview(),
            $message->getKeyboard(),
            $inlineMessageId,
        );
    }
}
