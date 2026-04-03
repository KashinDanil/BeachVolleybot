<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

use InvalidArgumentException;

readonly class TelegramUpdate
{
    public function __construct(
        public int $updateId,
        public ?TelegramMessage $message = null,
        public ?TelegramCallbackQuery $callbackQuery = null,
        public ?TelegramInlineQuery $inlineQuery = null,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        if (!isset($payload['message']) && !isset($payload['callback_query']) && !isset($payload['inline_query'])) {
            throw new InvalidArgumentException('Unsupported payload format');
        }

        return new self(
            updateId: $payload['update_id'],
            message: isset($payload['message'])
                ? TelegramMessage::fromArray($payload['message'])
                : null,
            callbackQuery: isset($payload['callback_query'])
                ? TelegramCallbackQuery::fromArray($payload['callback_query'])
                : null,
            inlineQuery: isset($payload['inline_query'])
                ? TelegramInlineQuery::fromArray($payload['inline_query'])
                : null,
        );
    }
}