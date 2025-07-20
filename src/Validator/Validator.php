<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator;

use BeachVolleybot\Common\State;
use BeachVolleybot\Validator\Rules\RuleInterface;

final readonly class Validator
{
    /** @var RuleInterface[] $rules */
    public function __construct(private array $rules)
    {
    }

    public function validate(): State
    {
        foreach ($this->rules as $rule) {
            if (!$rule->isValid()) {
                return State::error($rule->getError());
            }
        }

        return State::success();
    }
}
