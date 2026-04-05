<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Outgoing;

use BeachVolleybot\Errors\ErrorInterface;
use BeachVolleybot\Validator\Rules\TimeInTitleRule;

final readonly class InlineQueryError
{
    public const string UNKNOWN_TITLE = '⚠️ Something went wrong';
    public const string UNKNOWN_DESCRIPTION = 'Try again using the correct format';

    public const string TIME_NOT_FOUND_TITLE = '⚠️ Include a time in your query';
    public const string TIME_NOT_FOUND_DESCRIPTION = 'e.g., 18:00';

    private function __construct(
        private string $title,
        private string $description,
    ) {
    }

    public static function fromError(ErrorInterface $error): self
    {
        return match ($error->getMessageKey()) {
            TimeInTitleRule::ERROR_MESSAGE => new self(self::TIME_NOT_FOUND_TITLE, self::TIME_NOT_FOUND_DESCRIPTION),
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
