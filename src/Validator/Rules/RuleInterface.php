<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Errors\ValidationError;

interface RuleInterface
{
    public function isValid(): bool;

    public function getError(): ValidationError;
}
