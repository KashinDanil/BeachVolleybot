<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Errors\ValidationError;
use DateTimeImmutable;

readonly class KickoffInTheFutureRule implements RuleInterface
{
    public const string ERROR_MESSAGE = 'Game cannot be in the past';

    public function __construct(
        private string $title,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $now = null,
    ) {
    }

    public function isValid(): bool
    {
        $kickoff = GameDateTimeResolver::resolve($this->title, $this->createdAt);

        // No readable kickoff means the title itself is unusable, so it cannot pass.
        if (null === $kickoff) {
            return false;
        }

        return !GameDateTimeResolver::isKickoffPast($kickoff, $this->now);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['title' => $this->title]);
    }
}
