<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Errors\ValidationError;

readonly class ResolvableTimeRule implements RuleInterface
{
    public const string ERROR_MESSAGE = 'No time could be resolved from the text';

    public function __construct(private ?string $text)
    {
    }

    public function isValid(): bool
    {
        return null !== TimeExtractor::extract($this->text ?? '');
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['text' => $this->text]);
    }
}
