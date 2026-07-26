<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\SelectedTimeRule;
use PHPUnit\Framework\TestCase;

final class SelectedTimeRuleTest extends TestCase
{
    public function testAcceptsABareTime(): void
    {
        $this->assertTrue(new SelectedTimeRule('18:30')->isValid());
    }

    public function testRejectsTimeWithExtraText(): void
    {
        $this->assertFalse(new SelectedTimeRule('18:30 today')->isValid());
    }

    public function testRejectsANonTime(): void
    {
        $this->assertFalse(new SelectedTimeRule('noon')->isValid());
    }

    public function testRejectsNull(): void
    {
        $this->assertFalse(new SelectedTimeRule(null)->isValid());
    }
}
