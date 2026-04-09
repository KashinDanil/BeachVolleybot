<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Outgoing;

use BeachVolleybot\Errors\ErrorInterface;
use BeachVolleybot\Validator\Rules\DateTimeInTitleRule;

final readonly class InlineQueryError
{
    public const string UNKNOWN_TITLE = '⚠️ Something went wrong';
    public const string UNKNOWN_DESCRIPTION = 'Try again using the correct format';

    public const string DATE_AND_TIME_NOT_FOUND_TITLE = '⚠️ Include a date and time';
    public const string DATE_AND_TIME_NOT_FOUND_DESCRIPTION = 'E.g., Saturday 18:00';

    public const string DATE_NOT_FOUND_TITLE = '⚠️ Include a date';
    public const string DATE_NOT_FOUND_DESCRIPTION = 'E.g., Saturday or April 12';

    public const string TIME_NOT_FOUND_TITLE = '⚠️ Include a time';
    public const string TIME_NOT_FOUND_DESCRIPTION = 'E.g., 18:00';

    private function __construct(
        private string $title,
        private string $description,
    ) {
    }

    public static function fromError(ErrorInterface $error): self
    {
        return match ($error->getMessage()) {
            DateTimeInTitleRule::ERROR_DATE_AND_TIME_MISSING => new self(self::DATE_AND_TIME_NOT_FOUND_TITLE, self::DATE_AND_TIME_NOT_FOUND_DESCRIPTION),
            DateTimeInTitleRule::ERROR_DATE_MISSING => new self(self::DATE_NOT_FOUND_TITLE, self::DATE_NOT_FOUND_DESCRIPTION),
            DateTimeInTitleRule::ERROR_TIME_MISSING => new self(self::TIME_NOT_FOUND_TITLE, self::TIME_NOT_FOUND_DESCRIPTION),
            default => new self(self::UNKNOWN_TITLE, self::UNKNOWN_DESCRIPTION),
        };
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }
}
