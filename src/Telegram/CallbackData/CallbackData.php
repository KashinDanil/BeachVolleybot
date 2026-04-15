<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\CallbackData;

use BeachVolleybot\Processors\UpdateProcessors\CallbackAction;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;

final readonly class CallbackData implements CallbackDataInterface
{
    private const string KEY_ACTION          = 'a';
    private const string KEY_INLINE_QUERY_ID = 'q';

    private function __construct(
        private CallbackAction $action,
        private ?string $inlineQueryId = null,
    ) {
    }

    public static function create(CallbackAction $action): self
    {
        return new self($action);
    }

    public static function fromJson(?string $json): ?self
    {
        if (null === $json) {
            return null;
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $action = CallbackAction::tryFrom($data[self::KEY_ACTION] ?? '');

        if (null === $action) {
            return null;
        }

        return new self(
            action: $action,
            inlineQueryId: $data[self::KEY_INLINE_QUERY_ID] ?? null,
        );
    }

    public static function extractInlineQueryId(TelegramMessage $replyToMessage): ?string
    {
        $metaButton = $replyToMessage->replyMarkup?->inlineKeyboard[0][0] ?? null;

        if (null === $metaButton) {
            return null;
        }

        return self::fromJson($metaButton->callbackData)?->getInlineQueryId();
    }

    public function withInlineQueryId(string $inlineQueryId): self
    {
        return new self($this->action, $inlineQueryId);
    }

    public function getAction(): CallbackAction
    {
        return $this->action;
    }

    public function getInlineQueryId(): ?string
    {
        return $this->inlineQueryId;
    }

    public function jsonSerialize(): array
    {
        $data = [self::KEY_ACTION => $this->action->value];

        if (null !== $this->inlineQueryId) {
            $data[self::KEY_INLINE_QUERY_ID] = $this->inlineQueryId;
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR);
    }
}
