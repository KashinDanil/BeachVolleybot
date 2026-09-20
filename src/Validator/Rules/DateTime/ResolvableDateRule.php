<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules\DateTime;

use BeachVolleybot\Common\GameDateResolver;
use BeachVolleybot\Errors\ValidationError;
use BeachVolleybot\Validator\Rules\RuleInterface;
use DateTimeImmutable;

readonly class ResolvableDateRule implements RuleInterface
{
    public const string ERROR_MESSAGE = 'No date could be resolved from the text';

    public function __construct(
        private ?string $text,
        private DateTimeImmutable $now,
    ) {
    }

    public function isValid(): bool
    {
        return null !== GameDateResolver::resolve($this->text ?? '', $this->now);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['text' => $this->text]);
    }
}
