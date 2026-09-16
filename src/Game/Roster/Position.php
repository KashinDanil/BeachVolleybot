<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Roster;

final readonly class Position implements PositionInterface
{
    public function __construct(
        private int $number,
    ) {
    }

    public function first(): int
    {
        return $this->number;
    }

    public function last(): int
    {
        return $this->number;
    }

    public function slotCount(): int
    {
        return 1;
    }

    public function format(): string
    {
        return (string)$this->number;
    }
}
