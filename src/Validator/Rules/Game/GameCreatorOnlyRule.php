<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules\Game;

use BeachVolleybot\Errors\ValidationError;
use BeachVolleybot\Validator\Rules\RuleInterface;

readonly class GameCreatorOnlyRule implements RuleInterface
{
    public const string ERROR_MESSAGE = 'Only the game creator can do that';

    public function __construct(
        private int $senderId,
        private int $createdBy,
    ) {
    }

    public function isValid(): bool
    {
        return $this->senderId === $this->createdBy;
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, [
            'senderId' => $this->senderId,
            'createdBy' => $this->createdBy,
        ]);
    }
}
