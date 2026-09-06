<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\GameNotFinishedRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameNotFinishedRuleTest extends TestCase
{
    public function testValidWhenKickoffDayIsStillAhead(): void
    {
        $this->assertTrue($this->rule('2026-04-25 18:00', '2026-04-24 12:00')->isValid());
    }

    public function testValidForTheRestOfTheKickoffDay(): void
    {
        $this->assertTrue($this->rule('2026-04-24 10:00', '2026-04-24 23:59')->isValid());
    }

    public function testInvalidOnceTheKickoffDayIsOver(): void
    {
        $this->assertFalse($this->rule('2026-04-23 18:00', '2026-04-24 00:00')->isValid());
    }

    public function testErrorCarriesTheKickoff(): void
    {
        $error = $this->rule('2026-04-23 18:00', '2026-04-24 00:00')->getError();

        $this->assertSame(GameNotFinishedRule::ERROR_MESSAGE, $error->getMessage());
    }

    private function rule(string $kickoffAt, string $now): GameNotFinishedRule
    {
        return new GameNotFinishedRule(new DateTimeImmutable($kickoffAt), new DateTimeImmutable($now));
    }
}
