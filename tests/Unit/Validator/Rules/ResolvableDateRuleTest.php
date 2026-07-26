<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\ResolvableDateRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ResolvableDateRuleTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2099-06-01');
    }

    public function testAcceptsTextThatCarriesADate(): void
    {
        $this->assertTrue(new ResolvableDateRule('🏐 New game 31.12.2099', $this->now)->isValid());
    }

    public function testRejectsTextWithoutADate(): void
    {
        $this->assertFalse(new ResolvableDateRule('no date in here', $this->now)->isValid());
    }

    public function testRejectsNull(): void
    {
        $this->assertFalse(new ResolvableDateRule(null, $this->now)->isValid());
    }
}
