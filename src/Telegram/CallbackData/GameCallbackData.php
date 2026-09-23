<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\CallbackData;

use BeachVolleybot\Processors\UpdateProcessors\GameCallbackAction;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;

final readonly class GameCallbackData extends AbstractCallbackData
{
    private const string KEY_ACTION          = 'a';
    private const string KEY_GAME_KEY        = 'q';
    private const string KEY_INLINE_QUERY_ID = 'i';

    private function __construct(
        private GameCallbackAction $action,
        private ?string $gameKey = null,
        private ?string $inlineQueryId = null,
    ) {
    }

    public static function create(GameCallbackAction $action): self
    {
        return new self($action);
    }

    protected static function actionKey(): string
    {
        return self::KEY_ACTION;
    }

    protected static function parseAction(string $rawAction): ?CallbackActionInterface
    {
        return GameCallbackAction::tryFrom($rawAction);
    }

    /** @var GameCallbackAction $action */
    protected static function fromData(CallbackActionInterface $action, array $data): static
    {
        $gameKey = $data[self::KEY_GAME_KEY] ?? null;
        $inlineQueryId = $data[self::KEY_INLINE_QUERY_ID] ?? null;

        return new self(
            action: $action,
            gameKey: is_string($gameKey) ? $gameKey : null,
            inlineQueryId: is_string($inlineQueryId) ? $inlineQueryId : null,
        );
    }

    public static function extractGameKey(TelegramMessage $replyToMessage): ?string
    {
        $metaButton = $replyToMessage->replyMarkup?->inlineKeyboard[0][0] ?? null;

        if (null === $metaButton) {
            return null;
        }

        return self::fromJson($metaButton->callbackData)?->getGameKey();
    }

    public static function extractInlineQueryId(TelegramMessage $replyToMessage): ?string
    {
        $joinButton = $replyToMessage->replyMarkup?->inlineKeyboard[0][1] ?? null;

        if (null === $joinButton) {
            return null;
        }

        return self::fromJson($joinButton->callbackData)?->getInlineQueryId();
    }

    public function withGameKey(string $gameKey): self
    {
        return new self($this->action, $gameKey, $this->inlineQueryId);
    }

    public function withInlineQueryId(?string $inlineQueryId): self
    {
        return new self($this->action, $this->gameKey, $inlineQueryId);
    }

    public function getAction(): GameCallbackAction
    {
        return $this->action;
    }

    public function getGameKey(): ?string
    {
        return $this->gameKey;
    }

    public function getInlineQueryId(): ?string
    {
        return $this->inlineQueryId;
    }

    public function jsonSerialize(): array
    {
        $data = [self::KEY_ACTION => $this->action->value];

        if (null !== $this->gameKey) {
            $data[self::KEY_GAME_KEY] = $this->gameKey;
        }

        if (null !== $this->inlineQueryId) {
            $data[self::KEY_INLINE_QUERY_ID] = $this->inlineQueryId;
        }

        return $data;
    }
}
