<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\ValidPayloadRule;
use PHPUnit\Framework\TestCase;

final class ValidPayloadRuleTest extends TestCase
{
    public function testIsValidReturnsTrueForValidJson(): void
    {
        $this->assertTrue((new ValidPayloadRule('{"key":"value"}'))->isValid());
    }

    public function testIsValidReturnsTrueForJsonArray(): void
    {
        $this->assertTrue((new ValidPayloadRule('[1,2,3]'))->isValid());
    }

    public function testIsValidReturnsFalseForEmptyString(): void
    {
        $this->assertFalse((new ValidPayloadRule(''))->isValid());
    }

    public function testIsValidReturnsFalseForInvalidJson(): void
    {
        $this->assertFalse((new ValidPayloadRule('{invalid}'))->isValid());
    }

    public function testIsValidReturnsFalseForPlainString(): void
    {
        $this->assertFalse((new ValidPayloadRule('hello'))->isValid());
    }

    public function testGetErrorContainsPayload(): void
    {
        $error = (new ValidPayloadRule('{bad}'))->getError();

        $this->assertSame(['payload' => '{bad}'], $error->getData());
    }

    public function testGetErrorMessageDescribesIssue(): void
    {
        $error = (new ValidPayloadRule('{bad}'))->getError();

        $this->assertNotEmpty($error->getMessage());
    }
}