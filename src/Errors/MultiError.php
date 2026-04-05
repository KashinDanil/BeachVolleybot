<?php

declare(strict_types=1);

namespace BeachVolleybot\Errors;

final readonly class MultiError implements ErrorInterface
{
    /** @var ErrorInterface[] $errors */
    public function __construct(private array $errors)
    {
    }

    public function getMessage(): string
    {
        return implode('; ', array_map(static fn(ErrorInterface $e) => $e->getMessage(), $this->errors));
    }

    public function getTranslatedMessage(): string
    {
        return implode('; ', array_map(static fn(ErrorInterface $e) => $e->getTranslatedMessage(), $this->errors));
    }

    public function getData(): array
    {
        return array_merge(...array_map(static fn(ErrorInterface $e) => $e->getData(), $this->errors));
    }

    /** @return ErrorInterface[] */
    public function getErrors(): array
    {
        return $this->errors;
    }
}