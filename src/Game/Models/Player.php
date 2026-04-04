<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

readonly class Player implements PlayerInterface
{
    public function __construct(
        private string $number,
        private string $name,
        private ?string $link,
        private int $volleyball,
        private int $net,
        private ?string $time,
    ) {
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function getVolleyball(): int
    {
        return $this->volleyball;
    }

    public function getNet(): int
    {
        return $this->net;
    }

    public function getTime(): ?string
    {
        return $this->time;
    }
}
