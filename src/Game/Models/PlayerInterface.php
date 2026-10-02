<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

use BeachVolleybot\Game\Roster\PositionInterface;

interface PlayerInterface
{
    public function getTelegramUserId(): int;

    public function getPosition(): PositionInterface;

    public function getName(): string;

    public function getLink(): ?string;

    public function getVolleyball(): int;

    public function getNet(): int;

    public function getTime(): string;
}
