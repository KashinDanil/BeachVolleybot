<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\DateTimeInTitleRule;
use PHPUnit\Framework\TestCase;

final class DateTimeInTitleRuleTest extends TestCase
{
    public function testValidWithDateAndTime(): void
    {
        $rule = new DateTimeInTitleRule('Saturday 11.04 18:00');

        $this->assertTrue($rule->isValid());
    }

    public function testValidWithDayOfWeekAndTime(): void
    {
        $rule = new DateTimeInTitleRule('Saturday 18:00');

        $this->assertTrue($rule->isValid());
    }

    public function testValidWithTextDateAndTime(): void
    {
        $rule = new DateTimeInTitleRule('April 12 18:00');

        $this->assertTrue($rule->isValid());
    }

    public function testInvalidWhenBothMissing(): void
    {
        $rule = new DateTimeInTitleRule('Bogatell');

        $this->assertFalse($rule->isValid());
        $this->assertSame(DateTimeInTitleRule::ERROR_DATE_AND_TIME_MISSING, $rule->getError()->getMessage());
    }

    public function testInvalidWhenDateMissing(): void
    {
        $rule = new DateTimeInTitleRule('18:00 Bogatell');

        $this->assertFalse($rule->isValid());
        $this->assertSame(DateTimeInTitleRule::ERROR_DATE_MISSING, $rule->getError()->getMessage());
    }

    public function testInvalidWhenTimeMissing(): void
    {
        $rule = new DateTimeInTitleRule('Saturday 11.04 Bogatell');

        $this->assertFalse($rule->isValid());
        $this->assertSame(DateTimeInTitleRule::ERROR_TIME_MISSING, $rule->getError()->getMessage());
    }

    public function testInvalidWhenTimeMissingWithDayOfWeekOnly(): void
    {
        $rule = new DateTimeInTitleRule('Saturday Bogatell');

        $this->assertFalse($rule->isValid());
        $this->assertSame(DateTimeInTitleRule::ERROR_TIME_MISSING, $rule->getError()->getMessage());
    }

    public function testInvalidWhenDateOverlapsWithTime(): void
    {
        $rule = new DateTimeInTitleRule('April 12:30');

        $this->assertFalse($rule->isValid());
        $this->assertSame(DateTimeInTitleRule::ERROR_DATE_MISSING, $rule->getError()->getMessage());
    }

    public function testErrorContainsTitle(): void
    {
        $rule = new DateTimeInTitleRule('Bogatell');
        $rule->isValid();

        $this->assertSame(['title' => 'Bogatell'], $rule->getError()->getData());
    }

    public function testInvalidWhenEmpty(): void
    {
        $rule = new DateTimeInTitleRule('');

        $this->assertFalse($rule->isValid());
        $this->assertSame(DateTimeInTitleRule::ERROR_DATE_AND_TIME_MISSING, $rule->getError()->getMessage());
    }
}
