<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

abstract readonly class AbstractBitmask
{
    public function __construct(
        protected int $mask = 0,
    ) {
    }

    public static function fromInt(int $mask): static
    {
        return new static($mask);
    }

    public function toInt(): int
    {
        return $this->mask;
    }

    protected function hasBit(BitFlag $flag): bool
    {
        return 0 !== ($this->mask & $flag->bit());
    }

    protected function withBit(BitFlag $flag): static
    {
        return new static($this->mask | $flag->bit());
    }

    protected function withoutBit(BitFlag $flag): static
    {
        return new static($this->mask & ~$flag->bit());
    }
}
