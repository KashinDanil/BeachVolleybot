<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\KickoffInTheFutureRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class KickoffInTheFutureRuleTest extends TestCase
{
    public function testValidWhenKickoffInFuture(): void
    {
        $rule = new KickoffInTheFutureRule(
            title: 'Beach 31.12.26 18:00',
            createdAt: new DateTimeImmutable('2026-04-01 10:00'),
            now: new DateTimeImmutable('2026-04-24 12:00'),
        );

        $this->assertTrue($rule->isValid());
    }

    public function testInvalidWhenKickoffInPast(): void
    {
        $rule = new KickoffInTheFutureRule(
            title: 'Beach 01.01.20 18:00',
            createdAt: new DateTimeImmutable('2020-01-01 09:00'),
            now: new DateTimeImmutable('2026-04-24 12:00'),
        );

        $this->assertFalse($rule->isValid());
        $this->assertSame(KickoffInTheFutureRule::ERROR_MESSAGE, $rule->getError()->getMessage());
    }

    public function testValidWhenTitleHasNoResolvableKickoff(): void
    {
        $rule = new KickoffInTheFutureRule(
            title: 'Beach Game',
            createdAt: new DateTimeImmutable('2020-01-01 09:00'),
            now: new DateTimeImmutable('2026-04-24 12:00'),
        );

        $this->assertTrue($rule->isValid());
    }

    public function testErrorContainsTitle(): void
    {
        $rule = new KickoffInTheFutureRule(
            title: 'Beach 01.01.20 18:00',
            createdAt: new DateTimeImmutable('2020-01-01 09:00'),
            now: new DateTimeImmutable('2026-04-24 12:00'),
        );
        $rule->isValid();

        $this->assertSame(['title' => 'Beach 01.01.20 18:00'], $rule->getError()->getData());
    }
}
