<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

readonly class GameCreatorOrAdminRule extends GameCreatorOnlyRule
{
    public function __construct(
        int $senderId,
        int $createdBy,
        private bool $isAdmin,
    ) {
        parent::__construct($senderId, $createdBy);
    }

    public function isValid(): bool
    {
        return $this->isAdmin || parent::isValid();
    }
}
