<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Outgoing;

use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;

readonly class TelegramMessage
{
    public function __construct(
        private Text $text,
        private ?InlineKeyboardMarkup $keyboard = null,
    ) {
    }

    public function getText(): Text
    {
        return $this->text;
    }

    public function getKeyboard(): ?InlineKeyboardMarkup
    {
        return $this->keyboard;
    }
}
