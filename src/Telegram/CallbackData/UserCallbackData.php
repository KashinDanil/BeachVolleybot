<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\CallbackData;

use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;

final readonly class UserCallbackData implements PageableCallbackDataInterface
{
    private const string KEY_ACTION  = 'ua';
    private const string KEY_GAME_ID = 'g';
    private const string KEY_PAGE    = 'p';

    private function __construct(
        private UserCallbackAction $action,
        private ?int $gameId = null,
        private ?int $page = null,
    ) {
    }

    public static function create(UserCallbackAction $action): self
    {
        return new self($action);
    }

    public static function fromJson(?string $json): ?static
    {
        if (null === $json) {
            return null;
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $action = UserCallbackAction::tryFrom($data[self::KEY_ACTION] ?? '');

        if (null === $action) {
            return null;
        }

        return new self(
            action: $action,
            gameId: $data[self::KEY_GAME_ID] ?? null,
            page: $data[self::KEY_PAGE] ?? null,
        );
    }

    public function withGameId(int $gameId): self
    {
        return new self($this->action, $gameId, $this->page);
    }

    public function withPage(int $page): static
    {
        return new self($this->action, $this->gameId, $page);
    }

    public function getAction(): UserCallbackAction
    {
        return $this->action;
    }

    public function getGameId(): ?int
    {
        return $this->gameId;
    }

    public function getPage(): int
    {
        return $this->page ?? 1;
    }

    public function jsonSerialize(): array
    {
        $data = [self::KEY_ACTION => $this->action->value];

        if (null !== $this->gameId) {
            $data[self::KEY_GAME_ID] = $this->gameId;
        }

        if (null !== $this->page) {
            $data[self::KEY_PAGE] = $this->page;
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR);
    }
}
