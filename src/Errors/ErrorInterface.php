<?php

declare(strict_types=1);

namespace BeachVolleybot\Errors;

interface ErrorInterface
{
    public function getMessage(): string;

    public function getTranslatedMessage(): string;

    public function getData(): array;
}
