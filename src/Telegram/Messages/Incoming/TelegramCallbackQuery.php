<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Incoming;

use BeachVolleybot\Telegram\Messages\MessageAddress;

readonly class TelegramCallbackQuery
{
    public function __construct(
        public string $id,
        public TelegramUser $from,
        public string $chatInstance,
        public ?TelegramMessage $message = null,
        public ?string $inlineMessageId = null,
        public ?string $data = null,
    ) {
    }

    public function isInline(): bool
    {
        return null !== $this->inlineMessageId;
    }

    public function hasMessage(): bool
    {
        return null !== $this->message;
    }

    public function toMessageAddress(): MessageAddress
    {
        if (null !== $this->inlineMessageId) {
            return MessageAddress::inline($this->inlineMessageId);
        }

        return MessageAddress::chat($this->message->chat->id, $this->message->messageId);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            from: TelegramUser::fromArray($data['from']),
            chatInstance: $data['chat_instance'],
            message: isset($data['message']) ? TelegramMessage::fromArray($data['message']) : null,
            inlineMessageId: $data['inline_message_id'] ?? null,
            data: $data['data'] ?? null,
        );
    }
}
