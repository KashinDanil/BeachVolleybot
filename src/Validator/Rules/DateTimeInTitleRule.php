<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Common\GameDateResolver;
use BeachVolleybot\Errors\ValidationError;

class DateTimeInTitleRule implements RuleInterface
{
    public const string ERROR_DATE_AND_TIME_MISSING = 'Title does not contain a date or time';
    public const string ERROR_DATE_MISSING          = 'Title does not contain a date';
    public const string ERROR_TIME_MISSING          = 'Title does not contain a time';

    private string $errorMessage = self::ERROR_DATE_AND_TIME_MISSING;

    public function __construct(private readonly string $title)
    {
    }

    public function isValid(): bool
    {
        $rawTime = TimeExtractor::extractRaw($this->title);
        $hasTime = null !== $rawTime;
        $titleWithoutTime = null !== $rawTime ? str_replace($rawTime, '', $this->title) : $this->title;
        $hasDate = null !== GameDateResolver::extractRaw($titleWithoutTime);

        if (!$hasDate && !$hasTime) {
            $this->errorMessage = self::ERROR_DATE_AND_TIME_MISSING;

            return false;
        }

        if (!$hasDate) {
            $this->errorMessage = self::ERROR_DATE_MISSING;

            return false;
        }

        if (!$hasTime) {
            $this->errorMessage = self::ERROR_TIME_MISSING;

            return false;
        }

        return true;
    }

    public function getError(): ValidationError
    {
        return new ValidationError($this->errorMessage, ['title' => $this->title]);
    }
}
