<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\SelectedDateRule;
use PHPUnit\Framework\TestCase;

final class SelectedDateRuleTest extends TestCase
{
    public function testAcceptsAValidIsoDate(): void
    {
        $this->assertTrue(new SelectedDateRule('2099-12-31')->isValid());
    }

    public function testRejectsARolledOverDate(): void
    {
        // createFromFormat would turn 2099-02-30 into 2099-03-02; the round-trip check rejects it.
        $this->assertFalse(new SelectedDateRule('2099-02-30')->isValid());
    }

    public function testRejectsANonDate(): void
    {
        $this->assertFalse(new SelectedDateRule('not-a-date')->isValid());
    }

    public function testRejectsNull(): void
    {
        $this->assertFalse(new SelectedDateRule(null)->isValid());
    }

    public function testErrorCarriesTheOffendingValue(): void
    {
        $rule = new SelectedDateRule('2099-02-30');
        $rule->isValid();

        $this->assertSame(SelectedDateRule::ERROR_MESSAGE, $rule->getError()->getMessage());
        $this->assertSame(['date' => '2099-02-30'], $rule->getError()->getData());
    }
}
