<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\PostRequestRule;
use PHPUnit\Framework\TestCase;

final class PostRequestRuleTest extends TestCase
{
    public function testIsValidReturnsTrueForPost(): void
    {
        $this->assertTrue((new PostRequestRule('POST'))->isValid());
    }

    public function testIsValidReturnsFalseForGet(): void
    {
        $this->assertFalse((new PostRequestRule('GET'))->isValid());
    }

    public function testIsValidReturnsFalseForNull(): void
    {
        $this->assertFalse((new PostRequestRule(null))->isValid());
    }

    public function testIsValidReturnsFalseForEmptyString(): void
    {
        $this->assertFalse((new PostRequestRule(''))->isValid());
    }

    public function testIsValidReturnsFalseForLowercasePost(): void
    {
        $this->assertFalse((new PostRequestRule('post'))->isValid());
    }

    public function testGetErrorContainsRequestMethod(): void
    {
        $error = (new PostRequestRule('GET'))->getError();

        $this->assertSame(['request_method' => 'GET'], $error->getData());
    }

    public function testGetErrorMessageDescribesIssue(): void
    {
        $error = (new PostRequestRule('GET'))->getError();

        $this->assertStringContainsStringIgnoringCase('POST', $error->getTranslatedMessage());
    }
}