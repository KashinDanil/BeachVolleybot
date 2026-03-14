<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator;

use BeachVolleybot\Common\State;
use BeachVolleybot\Errors\MultiError;
use BeachVolleybot\Validator\Rules\RuleInterface;

final readonly class Validator
{
    /** @var RuleInterface[] $rules */
    public function __construct(private array $rules)
    {
    }

    /**
     * Validates rules in order and returns on the first failure.
     * Use when subsequent rules depend on earlier ones passing (e.g. checking auth before parsing payload).
     */
    public function validate(): State
    {
        foreach ($this->rules as $rule) {
            if (!$rule->isValid()) {
                return State::error($rule->getError());
            }
        }

        return State::success();
    }

    /**
     * Validates all rules regardless of failures and returns a {@see MultiError} containing every error found.
     * Use when rules are independent and all violations should be reported at once.
     */
    public function validateAll(): State
    {
        $errors = [];

        foreach ($this->rules as $rule) {
            if (!$rule->isValid()) {
                $errors[] = $rule->getError();
            }
        }

        if (!empty($errors)) {
            return State::error(new MultiError($errors));
        }

        return State::success();
    }
}
