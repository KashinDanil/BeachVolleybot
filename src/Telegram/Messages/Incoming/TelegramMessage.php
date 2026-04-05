<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Incoming;

readonly class TelegramMessage
{
    public function __construct(
        public int $messageId,
        public TelegramUser $from,
        public TelegramChat $chat,
        public int $date,
        public ?string $text = null,
        public ?array $entities = null,
        public ?array $replyMarkup = null,
        public ?self $replyToMessage = null,
        public ?TelegramUser $viaBot = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            messageId: $data['message_id'],
            from: TelegramUser::fromArray($data['from']),
            chat: TelegramChat::fromArray($data['chat']),
            date: $data['date'],
            text: $data['text'] ?? null,
            entities: $data['entities'] ?? null,
            replyMarkup: $data['reply_markup'] ?? null,
            replyToMessage: isset($data['reply_to_message']) ? self::fromArray($data['reply_to_message']) : null,
            viaBot: isset($data['via_bot']) ? TelegramUser::fromArray($data['via_bot']) : null,
        );
    }
}
