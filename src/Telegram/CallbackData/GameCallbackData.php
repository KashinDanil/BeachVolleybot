<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\CallbackData;

use BeachVolleybot\Processors\UpdateProcessors\GameCallbackAction;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use JsonException;

final readonly class GameCallbackData implements CallbackDataInterface
{
    private const string KEY_ACTION   = 'a';
    private const string KEY_GAME_KEY = 'q';

    private function __construct(
        private GameCallbackAction $action,
        private ?string $gameKey = null,
    ) {
    }

    public static function create(GameCallbackAction $action): self
    {
        return new self($action);
    }

    public static function fromJson(?string $json): ?static
    {
        if (null === $json) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $action = GameCallbackAction::tryFrom($data[self::KEY_ACTION] ?? '');

        if (null === $action) {
            return null;
        }

        $gameKey = $data[self::KEY_GAME_KEY] ?? null;

        return new self(
            action: $action,
            gameKey: is_string($gameKey) ? $gameKey : null,
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

    public function withGameKey(string $gameKey): self
    {
        return new self($this->action, $gameKey);
    }

    public function getAction(): GameCallbackAction
    {
        return $this->action;
    }

    public function getGameKey(): ?string
    {
        return $this->gameKey;
    }

    public function jsonSerialize(): array
    {
        $data = [self::KEY_ACTION => $this->action->value];

        if (null !== $this->gameKey) {
            $data[self::KEY_GAME_KEY] = $this->gameKey;
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR);
    }
}
