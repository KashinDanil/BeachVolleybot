<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

use BeachVolleybot\Errors\ErrorInterface;

readonly class State
{
    public function __construct(
        private bool $result,
        private ?ErrorInterface $error = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->result;
    }
    public function getError(): ErrorInterface
    {
        return $this->error;
    }

    public static function success(): static
    {
        return new static(true);
    }

    public static function error(ErrorInterface $error): static
    {
        return new static(false, $error);
    }
}
