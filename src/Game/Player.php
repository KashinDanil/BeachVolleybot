<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

readonly class Player implements PlayerInterface
{
    public function __construct(
        private string $number,
        private string $name,
        private ?string $link,
        private int $ball,
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

    public function getBall(): int
    {
        return $this->ball;
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
