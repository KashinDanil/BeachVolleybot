<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\KickoffDayInTheFutureRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class KickoffDayInTheFutureRuleTest extends TestCase
{
    public function testValidWhenKickoffDayInFuture(): void
    {
        $rule = new KickoffDayInTheFutureRule(
            title: 'Beach 31.12.26 18:00',
            createdAt: new DateTimeImmutable('2026-04-01 10:00'),
            now: new DateTimeImmutable('2026-04-24 12:00'),
        );

        $this->assertTrue($rule->isValid());
    }

    public function testValidWhenKickoffIsEarlierOnTheSameDay(): void
    {
        $rule = new KickoffDayInTheFutureRule(
            title: 'Beach 24.04.26 10:00',
            createdAt: new DateTimeImmutable('2026-04-01 10:00'),
            now: new DateTimeImmutable('2026-04-24 18:00'),
        );

        $this->assertTrue($rule->isValid());
    }

    public function testInvalidWhenKickoffDayInPast(): void
    {
        $rule = new KickoffDayInTheFutureRule(
            title: 'Beach 01.01.20 18:00',
            createdAt: new DateTimeImmutable('2020-01-01 09:00'),
            now: new DateTimeImmutable('2026-04-24 12:00'),
        );

        $this->assertFalse($rule->isValid());
        $this->assertSame(KickoffDayInTheFutureRule::ERROR_MESSAGE, $rule->getError()->getMessage());
    }

    public function testValidWhenTitleHasNoResolvableKickoff(): void
    {
        $rule = new KickoffDayInTheFutureRule(
            title: 'Beach Game',
            createdAt: new DateTimeImmutable('2020-01-01 09:00'),
            now: new DateTimeImmutable('2026-04-24 12:00'),
        );

        $this->assertTrue($rule->isValid());
    }

    public function testErrorContainsTitle(): void
    {
        $rule = new KickoffDayInTheFutureRule(
            title: 'Beach 01.01.20 18:00',
            createdAt: new DateTimeImmutable('2020-01-01 09:00'),
            now: new DateTimeImmutable('2026-04-24 12:00'),
        );
        $rule->isValid();

        $this->assertSame(['title' => 'Beach 01.01.20 18:00'], $rule->getError()->getData());
    }
}