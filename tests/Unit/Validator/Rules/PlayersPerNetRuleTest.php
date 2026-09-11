<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\PlayersPerNetRule;
use PHPUnit\Framework\TestCase;

final class PlayersPerNetRuleTest extends TestCase
{
    public function testAcceptsNullAsNoLimitAtAll(): void
    {
        $this->assertTrue(new PlayersPerNetRule(null)->isValid());
    }

    public function testAcceptsTheMinimum(): void
    {
        $this->assertTrue(new PlayersPerNetRule(PlayersPerNetRule::MINIMUM)->isValid());
    }

    public function testAcceptsAnythingAboveTheMinimum(): void
    {
        $this->assertTrue(new PlayersPerNetRule(PlayersPerNetRule::MINIMUM + 1)->isValid());
        $this->assertTrue(new PlayersPerNetRule(100)->isValid());
    }

    public function testRejectsJustBelowTheMinimum(): void
    {
        $this->assertFalse(new PlayersPerNetRule(PlayersPerNetRule::MINIMUM - 1)->isValid());
    }

    public function testRejectsZeroWhichWouldMakeEverybodyAReserve(): void
    {
        $this->assertFalse(new PlayersPerNetRule(0)->isValid());
    }

    public function testRejectsANegativeLimitThatCouldNeverApply(): void
    {
        $this->assertFalse(new PlayersPerNetRule(-4)->isValid());
    }

    public function testTheErrorCarriesTheRejectedValue(): void
    {
        $error = new PlayersPerNetRule(2)->getError();

        $this->assertSame(PlayersPerNetRule::ERROR_MESSAGE, $error->getMessage());
        $this->assertSame(['players_per_net' => 2], $error->getData());
    }
}
