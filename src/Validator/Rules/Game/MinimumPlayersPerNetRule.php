<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules\Game;

use BeachVolleybot\Errors\ValidationError;
use BeachVolleybot\Validator\Rules\RuleInterface;

readonly class MinimumPlayersPerNetRule implements RuleInterface
{
    /** Below this is not a net's worth of players. */
    public const int MINIMUM = 4;

    public const string ERROR_MESSAGE = 'Players per net is below the minimum';

    public function __construct(
        private ?int $playersPerNet,
    ) {
    }

    public function isValid(): bool
    {
        return null === $this->playersPerNet || self::MINIMUM <= $this->playersPerNet;
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['players_per_net' => $this->playersPerNet]);
    }
}
