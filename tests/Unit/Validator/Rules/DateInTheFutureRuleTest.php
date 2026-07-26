<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\DateInTheFutureRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DateInTheFutureRuleTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2099-06-15 14:00');
    }

    public function testAcceptsAFutureDate(): void
    {
        $this->assertTrue(new DateInTheFutureRule(new DateTimeImmutable('2099-06-16'), $this->now)->isValid());
    }

    public function testAcceptsTodayEvenLaterInTheDay(): void
    {
        // The chosen day has not elapsed yet, even though "now" is mid-afternoon.
        $this->assertTrue(new DateInTheFutureRule(new DateTimeImmutable('2099-06-15 00:00'), $this->now)->isValid());
    }

    public function testRejectsYesterday(): void
    {
        $this->assertFalse(new DateInTheFutureRule(new DateTimeImmutable('2099-06-14 23:59'), $this->now)->isValid());
    }

    public function testRejectsNull(): void
    {
        $this->assertFalse(new DateInTheFutureRule(null, $this->now)->isValid());
    }

    public function testErrorCarriesTheOffendingValue(): void
    {
        $rule = new DateInTheFutureRule(new DateTimeImmutable('2099-06-14'), $this->now);
        $rule->isValid();

        $this->assertSame(DateInTheFutureRule::ERROR_MESSAGE, $rule->getError()->getMessage());
        $this->assertSame(['date' => '2099-06-14'], $rule->getError()->getData());
    }
}
