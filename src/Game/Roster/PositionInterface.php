<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Roster;

interface PositionInterface
{
    public function first(): int;

    public function last(): int;

    public function slotCount(): int;

    public function format(): string;
}
