<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\MinimumPlayersPerNetRule;
use PHPUnit\Framework\TestCase;

final class MinimumPlayersPerNetRuleTest extends TestCase
{
    public function testAcceptsNullAsNoLimitAtAll(): void
    {
        $this->assertTrue(new MinimumPlayersPerNetRule(null)->isValid());
    }

    public function testAcceptsTheMinimum(): void
    {
        $this->assertTrue(new MinimumPlayersPerNetRule(MinimumPlayersPerNetRule::MINIMUM)->isValid());
    }

    public function testAcceptsAnythingAboveTheMinimum(): void
    {
        $this->assertTrue(new MinimumPlayersPerNetRule(MinimumPlayersPerNetRule::MINIMUM + 1)->isValid());
        $this->assertTrue(new MinimumPlayersPerNetRule(100)->isValid());
    }

    public function testRejectsJustBelowTheMinimum(): void
    {
        $this->assertFalse(new MinimumPlayersPerNetRule(MinimumPlayersPerNetRule::MINIMUM - 1)->isValid());
    }

    public function testRejectsZeroWhichWouldMakeEverybodyAReserve(): void
    {
        $this->assertFalse(new MinimumPlayersPerNetRule(0)->isValid());
    }

    public function testRejectsANegativeLimitThatCouldNeverApply(): void
    {
        $this->assertFalse(new MinimumPlayersPerNetRule(-4)->isValid());
    }

    public function testTheErrorCarriesTheRejectedValue(): void
    {
        $error = new MinimumPlayersPerNetRule(2)->getError();

        $this->assertSame(MinimumPlayersPerNetRule::ERROR_MESSAGE, $error->getMessage());
        $this->assertSame(['players_per_net' => 2], $error->getData());
    }
}
