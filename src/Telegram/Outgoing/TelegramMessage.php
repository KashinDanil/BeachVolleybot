<?php

namespace BeachVolleybot\Telegram\Outgoing;

use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;

readonly class TelegramMessage
{
    public function __construct(
        public string $text,
        public ?array $keyboard = null,
    ) {
    }

    public function getText(): Text
    {
        return new Text($this->text);
    }

    public function getKeyboard(): ?InlineKeyboardMarkup
    {
        if (null !== $this->keyboard) {
            return new InlineKeyboardMarkup($this->keyboard);
        }

        return null;
    }
}