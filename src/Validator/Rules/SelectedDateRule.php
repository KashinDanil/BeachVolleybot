<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Errors\ValidationError;
use DateTimeImmutable;

readonly class SelectedDateRule implements RuleInterface
{
    public const string ERROR_MESSAGE = 'Selected date is not a valid ISO-8601 date';

    private const string FORMAT = '!Y-m-d';

    public function __construct(private ?string $date)
    {
    }

    public function isValid(): bool
    {
        if (null === $this->date) {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat(self::FORMAT, $this->date);

        return false !== $parsed && $this->date === $parsed->format('Y-m-d');
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['date' => $this->date]);
    }
}
