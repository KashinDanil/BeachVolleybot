<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\CallbackData;

use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;

final readonly class NewGameCallbackData extends AbstractCallbackData implements PageableCallbackDataInterface
{
    private const string KEY_ACTION = 'na';
    private const string KEY_DATE   = 'd';
    private const string KEY_TIME   = 't';
    private const string KEY_VENUE  = 'v';
    private const string KEY_PAGE   = 'p';

    private function __construct(
        private NewGameCallbackAction $action,
        private ?string $date = null,
        private ?string $time = null,
        private ?string $venueName = null,
        private ?int $page = null,
    ) {
    }

    public static function create(NewGameCallbackAction $action): self
    {
        return new self($action);
    }

    protected static function actionKey(): string
    {
        return self::KEY_ACTION;
    }

    protected static function parseAction(string $rawAction): ?CallbackActionInterface
    {
        return NewGameCallbackAction::tryFrom($rawAction);
    }

    /** @var NewGameCallbackAction $action */
    protected static function fromData(CallbackActionInterface $action, array $data): static
    {
        $date = $data[self::KEY_DATE] ?? null;
        $time = $data[self::KEY_TIME] ?? null;
        $venueName = $data[self::KEY_VENUE] ?? null;
        $page = $data[self::KEY_PAGE] ?? null;

        return new self(
            action: $action,
            date: is_string($date) ? $date : null,
            time: is_string($time) ? $time : null,
            venueName: is_string($venueName) ? $venueName : null,
            page: is_int($page) ? $page : null,
        );
    }

    public function withDate(string $date): self
    {
        return new self($this->action, $date, $this->time, $this->venueName, $this->page);
    }

    public function withTime(string $time): self
    {
        return new self($this->action, $this->date, $time, $this->venueName, $this->page);
    }

    public function withVenueName(string $venueName): self
    {
        return new self($this->action, $this->date, $this->time, $venueName, $this->page);
    }

    public function withPage(int $page): static
    {
        return new self($this->action, $this->date, $this->time, $this->venueName, $page);
    }

    public function getAction(): NewGameCallbackAction
    {
        return $this->action;
    }

    public function getDate(): ?string
    {
        return $this->date;
    }

    public function getTime(): ?string
    {
        return $this->time;
    }

    public function getVenueName(): ?string
    {
        return $this->venueName;
    }

    public function getPage(): int
    {
        return $this->page ?? 1;
    }

    public function jsonSerialize(): array
    {
        $data = [self::KEY_ACTION => $this->action->value];

        if (null !== $this->date) {
            $data[self::KEY_DATE] = $this->date;
        }

        if (null !== $this->time) {
            $data[self::KEY_TIME] = $this->time;
        }

        if (null !== $this->venueName) {
            $data[self::KEY_VENUE] = $this->venueName;
        }

        if (null !== $this->page) {
            $data[self::KEY_PAGE] = $this->page;
        }

        return $data;
    }
}
