<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Errors\ValidationError;
use DateTimeImmutable;

readonly class DateInTheFutureRule implements RuleInterface
{
    public const string ERROR_MESSAGE = 'Selected date is in the past';

    public function __construct(
        private ?DateTimeImmutable $date,
        private DateTimeImmutable $now,
    ) {
    }

    public function isValid(): bool
    {
        if (null === $this->date) {
            return false;
        }

        return $this->date->setTime(0, 0) >= $this->now->setTime(0, 0);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['date' => $this->date?->format('Y-m-d')]);
    }
}
