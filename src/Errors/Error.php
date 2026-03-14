<?php

declare(strict_types=1);

namespace BeachVolleybot\Errors;

use BeachVolleybot\Localization\Translator;

abstract readonly class Error implements ErrorInterface
{
    public function __construct(protected string $message, protected array $data = [])
    {
    }

    public function getMessage(): string
    {
        return Translator::translate($this->message);
    }

    public function getData(): array
    {
        return $this->data;
    }
}
