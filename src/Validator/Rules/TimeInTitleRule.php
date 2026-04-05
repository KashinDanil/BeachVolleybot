<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Common\TimeExtractor;
use BeachVolleybot\Errors\ValidationError;

readonly class TimeInTitleRule implements RuleInterface
{
    public const string ERROR_MESSAGE = 'Title does not contain a time';

    public function __construct(private string $title)
    {
    }

    public function isValid(): bool
    {
        return null !== TimeExtractor::extract($this->title);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['title' => $this->title]);
    }
}