<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\CallbackData;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;

final readonly class AdminCallbackData extends AbstractCallbackData implements PageableCallbackDataInterface
{
    private const string KEY_ACTION   = 'aa';
    private const string KEY_GAME_ID  = 'g';
    private const string KEY_USER_ID  = 'u';
    private const string KEY_PAGE     = 'p';
    private const string KEY_FILENAME = 'f';

    private function __construct(
        private AdminCallbackAction $action,
        private ?int $gameId = null,
        private ?int $userId = null,
        private ?int $page = null,
        private ?string $filename = null,
    ) {
    }

    public static function create(AdminCallbackAction $action): self
    {
        return new self($action);
    }

    protected static function actionKey(): string
    {
        return self::KEY_ACTION;
    }

    protected static function parseAction(string $rawAction): ?CallbackActionInterface
    {
        return AdminCallbackAction::tryFrom($rawAction);
    }

    /** @var AdminCallbackAction $action */
    protected static function fromData(CallbackActionInterface $action, array $data): static
    {
        return new self(
            action: $action,
            gameId: $data[self::KEY_GAME_ID] ?? null,
            userId: $data[self::KEY_USER_ID] ?? null,
            page: $data[self::KEY_PAGE] ?? null,
            filename: $data[self::KEY_FILENAME] ?? null,
        );
    }

    public function withGameId(int $gameId): self
    {
        return new self($this->action, $gameId, $this->userId, $this->page, $this->filename);
    }

    public function withUserId(int $userId): self
    {
        return new self($this->action, $this->gameId, $userId, $this->page, $this->filename);
    }

    public function withPage(int $page): static
    {
        return new self($this->action, $this->gameId, $this->userId, $page, $this->filename);
    }

    public function withFilename(string $filename): self
    {
        return new self($this->action, $this->gameId, $this->userId, $this->page, $filename);
    }

    public function getAction(): AdminCallbackAction
    {
        return $this->action;
    }

    public function getGameId(): ?int
    {
        return $this->gameId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getPage(): int
    {
        return $this->page ?? 1;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function jsonSerialize(): array
    {
        $data = [self::KEY_ACTION => $this->action->value];

        if (null !== $this->gameId) {
            $data[self::KEY_GAME_ID] = $this->gameId;
        }

        if (null !== $this->userId) {
            $data[self::KEY_USER_ID] = $this->userId;
        }

        if (null !== $this->page) {
            $data[self::KEY_PAGE] = $this->page;
        }

        if (null !== $this->filename) {
            $data[self::KEY_FILENAME] = $this->filename;
        }

        return $data;
    }
}
