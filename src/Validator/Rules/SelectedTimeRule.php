<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Errors\ValidationError;

readonly class SelectedTimeRule implements RuleInterface
{
    public const string ERROR_MESSAGE = 'Selected time is not a valid time';

    public function __construct(private ?string $time)
    {
    }

    public function isValid(): bool
    {
        return null !== $this->time && TimeExtractor::isTimeOnly($this->time);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['time' => $this->time]);
    }
}
