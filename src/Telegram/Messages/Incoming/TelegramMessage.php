<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Incoming;

use JsonSerializable;

readonly class TelegramMessage implements JsonSerializable
{
    public function __construct(
        public int $messageId,
        public TelegramUser $from,
        public TelegramChat $chat,
        public int $date,
        public ?string $text = null,
        public ?array $entities = null,
        public ?TelegramLocation $location = null,
        public ?TelegramInlineKeyboardMarkup $replyMarkup = null,
        public ?self $replyToMessage = null,
        public ?TelegramUser $viaBot = null,
        public ?self $pinnedMessage = null,
        private array $rawPayload = [],
    ) {
    }

    public function hasText(): bool
    {
        return null !== $this->text;
    }

    public function hasLocation(): bool
    {
        return null !== $this->location;
    }

    public function isViaThisBot(): bool
    {
        return $this->viaBot?->isThisBot() ?? false;
    }

    public function hasInlineKeyboard(): bool
    {
        return null !== $this->replyMarkup;
    }

    public function hasReplyToMessage(): bool
    {
        return null !== $this->replyToMessage;
    }

    public function isPinMessage(): bool
    {
        return null !== $this->pinnedMessage;
    }

    public function jsonSerialize(): array
    {
        return $this->rawPayload;
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR);
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
            location: isset($data['location']) ? TelegramLocation::fromArray($data['location']) : null,
            replyMarkup: isset($data['reply_markup']) ? TelegramInlineKeyboardMarkup::fromArray($data['reply_markup']) : null,
            replyToMessage: isset($data['reply_to_message']) ? self::fromArray($data['reply_to_message']) : null,
            viaBot: isset($data['via_bot']) ? TelegramUser::fromArray($data['via_bot']) : null,
            pinnedMessage: isset($data['pinned_message']) ? self::fromArray($data['pinned_message']) : null,
        );
    }
}
