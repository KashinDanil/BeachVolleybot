<?php

declare(strict_types=1);

namespace BeachVolleybot\Errors;

interface ErrorInterface
{
    public function getMessageKey(): string;

    public function getMessage(): string;

    public function getData(): array;
}
