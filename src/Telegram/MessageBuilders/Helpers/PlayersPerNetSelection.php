<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Helpers;

use BeachVolleybot\Validator\Rules\Game\MinimumPlayersPerNetRule;

final readonly class PlayersPerNetSelection
{
    public const int DEFAULT = 6;
    private const int MAXIMUM_PLAYERS_PER_NET = 12;

    private int $value;

    private function __construct(
        int $value,
        private bool $applied,
    ) {
        $this->value = max(MinimumPlayersPerNetRule::MINIMUM, min($value, self::MAXIMUM_PLAYERS_PER_NET));
    }

    public static function applied(int $value): self
    {
        return new self($value, true);
    }

    public static function pending(int $value): self
    {
        return new self($value, false);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function isApplied(): bool
    {
        return $this->applied;
    }

    public function appliedValue(): ?int
    {
        return $this->applied ? $this->value : null;
    }

    public function canDecrease(): bool
    {
        return MinimumPlayersPerNetRule::MINIMUM < $this->value;
    }

    public function canIncrease(): bool
    {
        return self::MAXIMUM_PLAYERS_PER_NET > $this->value;
    }

    public function decreased(): self
    {
        return new self($this->value - 1, $this->applied);
    }

    public function increased(): self
    {
        return new self($this->value + 1, $this->applied);
    }
}
