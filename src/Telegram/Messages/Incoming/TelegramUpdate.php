<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Incoming;

use JsonSerializable;

readonly class TelegramUpdate implements JsonSerializable
{
    public function __construct(
        public int $updateId,
        public ?TelegramMessage $message = null,
        public ?TelegramMessage $editedMessage = null,
        public ?TelegramCallbackQuery $callbackQuery = null,
        public ?TelegramInlineQuery $inlineQuery = null,
        public ?TelegramChosenInlineResult $chosenInlineResult = null,
        private array $rawPayload = [],
    ) {
    }

    public function hasMessage(): bool
    {
        return null !== $this->message;
    }

    public function hasEditedMessage(): bool
    {
        return null !== $this->editedMessage;
    }

    public function hasCallbackQuery(): bool
    {
        return null !== $this->callbackQuery;
    }

    public function hasInlineQuery(): bool
    {
        return null !== $this->inlineQuery;
    }

    public function hasChosenInlineResult(): bool
    {
        return null !== $this->chosenInlineResult;
    }

    public function jsonSerialize(): array
    {
        return $this->rawPayload;
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            updateId: $payload['update_id'],
            message: isset($payload['message'])
                ? TelegramMessage::fromArray($payload['message'])
                : null,
            editedMessage: isset($payload['edited_message'])
                ? TelegramMessage::fromArray($payload['edited_message'])
                : null,
            callbackQuery: isset($payload['callback_query'])
                ? TelegramCallbackQuery::fromArray($payload['callback_query'])
                : null,
            inlineQuery: isset($payload['inline_query'])
                ? TelegramInlineQuery::fromArray($payload['inline_query'])
                : null,
            chosenInlineResult: isset($payload['chosen_inline_result'])
                ? TelegramChosenInlineResult::fromArray($payload['chosen_inline_result'])
                : null,
            rawPayload: $payload,
        );
    }
}
