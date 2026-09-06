<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Errors\ValidationError;
use DateTimeImmutable;

/**
 * A saved game stays open until its kickoff day is over, judged on the stored kickoff.
 * Text that is not a game yet goes through {@see KickoffDayInTheFutureRule} instead.
 */
readonly class GameNotFinishedRule implements RuleInterface
{
    public const string ERROR_MESSAGE = 'Game has already finished';

    public function __construct(
        private DateTimeImmutable $kickoffAt,
        private ?DateTimeImmutable $now = null,
    ) {
    }

    public function isValid(): bool
    {
        return !GameDateTimeResolver::isKickoffDayPast($this->kickoffAt, $this->now);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['kickoffAt' => $this->kickoffAt->format('Y-m-d H:i')]);
    }
}
