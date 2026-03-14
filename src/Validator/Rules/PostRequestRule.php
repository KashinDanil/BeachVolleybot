<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Errors\ValidationError;

readonly class PostRequestRule implements RuleInterface
{
    private const string ALLOWED_METHOD = 'POST';
    private const string ERROR_MESSAGE = 'Invalid request method. Only POST requests are allowed';

    public function __construct(private ?string $requestMethod)
    {
    }

    public function isValid(): bool
    {
        return self::ALLOWED_METHOD === $this->requestMethod;
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['request_method' => $this->requestMethod]);
    }
}
