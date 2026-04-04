<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

interface PlayerInterface
{
    public function getNumber(): string;

    public function getName(): string;

    public function getLink(): ?string;

    public function getBall(): int;

    public function getNet(): int;

    public function getTime(): ?string;
}
