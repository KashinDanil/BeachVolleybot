<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Incoming;

readonly class TelegramInlineKeyboardMarkup
{
    /**
     * @param TelegramInlineKeyboardButton[][] $inlineKeyboard
     */
    public function __construct(
        public array $inlineKeyboard,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $rows = [];

        foreach ($data['inline_keyboard'] as $row) {
            $buttons = [];

            foreach ($row as $button) {
                $buttons[] = TelegramInlineKeyboardButton::fromArray($button);
            }

            $rows[] = $buttons;
        }

        return new self(inlineKeyboard: $rows);
    }
}
