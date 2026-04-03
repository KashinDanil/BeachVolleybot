<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

readonly class TelegramChosenInlineResult
{
    public function __construct(
        public string $resultId,
        public TelegramUser $from,
        public string $query,
        public ?string $inlineMessageId = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            resultId: $data['result_id'],
            from: TelegramUser::fromArray($data['from']),
            query: $data['query'],
            inlineMessageId: $data['inline_message_id'] ?? null,
        );
    }
}