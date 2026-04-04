<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

interface PlayerInterface
{
    public function getNumber(): string;

    public function getName(): string;

    public function getLink(): ?string;

    public function getVolleyball(): int;

    public function getNet(): int;

    public function getTime(): ?string;
}
