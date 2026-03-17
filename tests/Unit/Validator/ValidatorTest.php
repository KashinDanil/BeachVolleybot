<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator;

use BeachVolleybot\Errors\MultiError;
use BeachVolleybot\Errors\ValidationError;
use BeachVolleybot\Validator\Rules\RuleInterface;
use BeachVolleybot\Validator\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidateReturnsSuccessWhenAllRulesPass(): void
    {
        $validator = new Validator([$this->passingRule(), $this->passingRule()]);

        $this->assertTrue($validator->validate()->isSuccess());
    }

    public function testValidateReturnsErrorOnFirstFailingRule(): void
    {
        $error     = new ValidationError('first failure');
        $validator = new Validator([$this->failingRule($error), $this->passingRule()]);

        $state = $validator->validate();

        $this->assertFalse($state->isSuccess());
        $this->assertSame($error, $state->getError());
    }

    public function testValidateStopsAtFirstFailure(): void
    {
        $first  = new ValidationError('first');
        $second = new ValidationError('second');

        $validator = new Validator([$this->failingRule($first), $this->failingRule($second)]);

        $this->assertSame($first, $validator->validate()->getError());
    }

    public function testValidateReturnsSuccessWithNoRules(): void
    {
        $this->assertTrue((new Validator([]))->validate()->isSuccess());
    }

    public function testValidateAllReturnsSuccessWhenAllRulesPass(): void
    {
        $validator = new Validator([$this->passingRule(), $this->passingRule()]);

        $this->assertTrue($validator->validateAll()->isSuccess());
    }

    public function testValidateAllReturnsErrorWhenAnyRuleFails(): void
    {
        $validator = new Validator([$this->failingRule(new ValidationError('e')), $this->passingRule()]);

        $this->assertFalse($validator->validateAll()->isSuccess());
    }

    public function testValidateAllCollectsAllErrors(): void
    {
        $e1 = new ValidationError('first');
        $e2 = new ValidationError('second');

        $validator = new Validator([$this->failingRule($e1), $this->failingRule($e2)]);

        $state = $validator->validateAll();
        $this->assertInstanceOf(MultiError::class, $state->getError());
        $this->assertSame([$e1, $e2], $state->getError()->getErrors());
    }

    public function testValidateAllReturnsSuccessWithNoRules(): void
    {
        $this->assertTrue((new Validator([]))->validateAll()->isSuccess());
    }

    private function passingRule(): RuleInterface
    {
        $rule = $this->createStub(RuleInterface::class);
        $rule->method('isValid')->willReturn(true);

        return $rule;
    }

    private function failingRule(ValidationError $error): RuleInterface
    {
        $rule = $this->createStub(RuleInterface::class);
        $rule->method('isValid')->willReturn(false);
        $rule->method('getError')->willReturn($error);

        return $rule;
    }
}