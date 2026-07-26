<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\ResolvableTimeRule;
use PHPUnit\Framework\TestCase;

final class ResolvableTimeRuleTest extends TestCase
{
    public function testAcceptsTextThatCarriesATime(): void
    {
        $this->assertTrue(new ResolvableTimeRule('🕒 18:30')->isValid());
    }

    public function testRejectsTextWithoutATime(): void
    {
        $this->assertFalse(new ResolvableTimeRule('📅 31.12.2099')->isValid());
    }

    public function testRejectsNull(): void
    {
        $this->assertFalse(new ResolvableTimeRule(null)->isValid());
    }
}
