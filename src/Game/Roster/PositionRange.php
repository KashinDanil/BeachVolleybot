<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Roster;

final readonly class PositionRange implements PositionInterface
{
    public function __construct(
        private int $first,
        private int $last,
    ) {
    }

    public function first(): int
    {
        return $this->first;
    }

    public function last(): int
    {
        return $this->last;
    }

    public function slotCount(): int
    {
        return $this->last - $this->first + 1;
    }

    public function format(): string
    {
        return $this->first . '-' . $this->last;
    }
}
